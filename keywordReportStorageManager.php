const TABLE_NAME = 'keyword_insight_reports';

	public static function installTables() {
		global $wpdb;
		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			keyword VARCHAR(255) NOT NULL,
			search_volume BIGINT UNSIGNED DEFAULT NULL,
			difficulty FLOAT DEFAULT NULL,
			report_data LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY keyword_idx (keyword(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Stores a keyword report in the database.
	 *
	 * @param array $data {
	 *     @type string       keyword        The keyword.
	 *     @type int|null     search_volume  Search volume, optional.
	 *     @type float|null   difficulty     Keyword difficulty, optional.
	 *     @type array        report_data    Arbitrary report data to JSON-encode.
	 * }
	 * @return int|WP_Error Insert ID on success, WP_Error on failure.
	 */
	public static function storeKeywordReport( array $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$keyword = sanitize_text_field( $data['keyword'] ?? '' );
		if ( '' === $keyword ) {
			return new \WP_Error(
				'invalid_keyword',
				__( 'Keyword is required.', 'keyword-insight-pro' )
			);
		}

		$json = wp_json_encode( $data['report_data'] ?? [] );
		if ( false === $json || JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'json_encode_error',
				__( 'Failed to encode report data to JSON.', 'keyword-insight-pro' ),
				json_last_error_msg()
			);
		}

		$insert_data   = [
			'keyword'     => $keyword,
			'report_data' => $json,
			'created_at'  => current_time( 'mysql', true ),
		];
		$formats       = [ '%s', '%s', '%s' ];
		// Handle optional numeric fields: include only if provided as numeric
		if ( isset( $data['search_volume'] ) && is_numeric( $data['search_volume'] ) ) {
			$insert_data['search_volume'] = intval( $data['search_volume'] );
			$formats[]                    = '%d';
		}
		if ( isset( $data['difficulty'] ) && is_numeric( $data['difficulty'] ) ) {
			$insert_data['difficulty'] = floatval( $data['difficulty'] );
			$formats[]                 = '%f';
		}

		$result = $wpdb->insert( $table_name, $insert_data, $formats );
		if ( false === $result ) {
			return new \WP_Error(
				'db_insert_error',
				__( 'Could not insert keyword report.', 'keyword-insight-pro' ),
				$wpdb->last_error
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Retrieves a keyword report by ID.
	 *
	 * @param int $id Report ID.
	 * @return object|WP_Error Report row object on success, WP_Error on failure.
	 */
	public static function getReport( $id ) {
		global $wpdb;
		$id = intval( $id );
		if ( $id <= 0 ) {
			return new \WP_Error(
				'invalid_id',
				__( 'Invalid report ID.', 'keyword-insight-pro' )
			);
		}
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$row        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id )
		);
		if ( null === $row ) {
			return new \WP_Error(
				'not_found',
				__( 'Keyword report not found.', 'keyword-insight-pro' )
			);
		}
		return $row;
	}
}