public function __construct() {
        $this->authenticate();
    }

    public function authenticate() {
        $clientId     = get_option( 'kip_google_client_id' );
        $clientSecret = get_option( 'kip_google_client_secret' );
        $redirectUri  = get_option( 'kip_google_redirect_uri', admin_url( 'admin.php?page=keywordinsightpro_settings' ) );
        $this->viewId = get_option( 'kip_google_view_id' );

        if ( empty( $clientId ) || empty( $clientSecret ) || empty( $this->viewId ) ) {
            throw new Exception( 'Google API credentials or view ID not configured.' );
        }

        $client = new Google_Client();
        $client->setClientId( $clientId );
        $client->setClientSecret( $clientSecret );
        $client->setRedirectUri( $redirectUri );
        $client->setAccessType( 'offline' );

        // Only prompt consent if there is no stored refresh token
        $encryptedRefreshToken = get_option( 'kip_google_refresh_token' );
        $refreshToken = $encryptedRefreshToken ? $this->decrypt_data( $encryptedRefreshToken ) : null;
        if ( ! $refreshToken ) {
            $client->setPrompt( 'consent' );
        }

        $client->addScope( Google_Service_AnalyticsReporting::ANALYTICS_READONLY );

        // Load tokens
        $encryptedAccessToken = get_option( 'kip_google_access_token' );
        $accessToken = $encryptedAccessToken ? $this->decrypt_data( $encryptedAccessToken ) : null;

        if ( $refreshToken ) {
            $client->refreshToken( $refreshToken );
            $client->setAccessToken( $client->getAccessToken() );
        }

        if ( $accessToken ) {
            $client->setAccessToken( $accessToken );
        }

        // Refresh if expired
        if ( $client->isAccessTokenExpired() ) {
            $newToken = $refreshToken
                ? $client->fetchAccessTokenWithRefreshToken( $refreshToken )
                : $client->fetchAccessTokenWithAuthCode( isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '' );

            if ( isset( $newToken['error'] ) ) {
                error_log( 'Google token refresh error: ' . $newToken['error_description'] );
                delete_option( 'kip_google_refresh_token' );
                delete_option( 'kip_google_access_token' );
                throw new Exception( 'Failed to refresh Google API token. Please reconnect.' );
            }

            if ( isset( $newToken['refresh_token'] ) ) {
                update_option( 'kip_google_refresh_token', $this->encrypt_data( $newToken['refresh_token'] ) );
            }
            if ( isset( $newToken['access_token'] ) ) {
                $client->setAccessToken( $newToken );
                update_option( 'kip_google_access_token', $this->encrypt_data( wp_json_encode( $newToken ) ) );
            }
        }

        $this->client = $client;
        return $this->client;
    }

    public function fetchMetrics( array $keywords, $startDate = '30daysAgo', $endDate = 'today' ) {
        try {
            if ( empty( $keywords ) ) {
                return [ 'reports' => [] ];
            }
            if ( ! $this->client ) {
                $this->authenticate();
            }
            $this->analytics = new Google_Service_AnalyticsReporting( $this->client );

            $dateRange = new Google_Service_AnalyticsReporting_DateRange();
            $dateRange->setStartDate( $startDate );
            $dateRange->setEndDate( $endDate );

            $metrics = [
                ( new Google_Service_AnalyticsReporting_Metric() )->setExpression( 'ga:sessions' )->setAlias( 'sessions' ),
                ( new Google_Service_AnalyticsReporting_Metric() )->setExpression( 'ga:impressions' )->setAlias( 'impressions' ),
                ( new Google_Service_AnalyticsReporting_Metric() )->setExpression( 'ga:CTR' )->setAlias( 'ctr' ),
                ( new Google_Service_AnalyticsReporting_Metric() )->setExpression( 'ga:avgPosition' )->setAlias( 'avg_position' ),
            ];

            $dimensions = [
                ( new Google_Service_AnalyticsReporting_Dimension() )->setName( 'ga:keyword' ),
            ];

            $allRows = [];
            $reportHeader = null;
            $chunks = array_chunk( $keywords, 10 );

            foreach ( $chunks as $chunk ) {
                $dimensionFilter = new Google_Service_AnalyticsReporting_DimensionFilter();
                $dimensionFilter->setDimensionName( 'ga:keyword' );
                $dimensionFilter->setOperator( 'IN_LIST' );
                $dimensionFilter->setExpressions( $chunk );

                $filterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
                $filterClause->setFilters( [ $dimensionFilter ] );

                $request = new Google_Service_AnalyticsReporting_ReportRequest();
                $request->setViewId( $this->viewId );
                $request->setDateRanges( [ $dateRange ] );
                $request->setMetrics( $metrics );
                $request->setDimensions( $dimensions );
                $request->setDimensionFilterClauses( [ $filterClause ] );

                $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
                $body->setReportRequests( [ $request ] );

                $response = $this->analytics->reports->batchGet( $body );
                $array    = json_decode( wp_json_encode( $response ), true );
                if ( isset( $array['reports'][0] ) ) {
                    $report = $array['reports'][0];
                    if ( ! $reportHeader ) {
                        $reportHeader = $report['columnHeader'];
                    }
                    if ( isset( $report['data']['rows'] ) ) {
                        $allRows = array_merge( $allRows, $report['data']['rows'] );
                    }
                }
            }

            return [
                'reports' => [
                    [
                        'columnHeader' => $reportHeader,
                        'data'         => [ 'rows' => $allRows ],
                    ],
                ],
            ];
        } catch ( Exception $e ) {
            return [ 'error' => $e->getMessage() ];
        }
    }

    private function get_encryption_key() {
        if ( ! defined( 'AUTH_SALT' ) ) {
            throw new Exception( 'AUTH_SALT is not defined.' );
        }
        return hash( 'sha256', AUTH_SALT, true );
    }

    private function encrypt_data( $plain ) {
        $key = $this->get_encryption_key();
        $iv  = openssl_random_pseudo_bytes( 16 );
        $enc = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $enc );
    }

    private function decrypt_data( $cipher ) {
        $key  = $this->get_encryption_key();
        $raw  = base64_decode( $cipher );
        $iv   = substr( $raw, 0, 16 );
        $data = substr( $raw, 16 );
        return openssl_decrypt( $data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
    }
}