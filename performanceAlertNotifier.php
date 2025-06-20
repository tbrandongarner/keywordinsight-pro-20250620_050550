public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->register_hooks();
        }
    }

    protected function register_hooks() {
        add_action( 'keywordinsight_after_metrics_fetch', array( $this, 'check_thresholds' ), 10, 1 );
        add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
        add_action( 'keywordinsight_send_clicks_alert_email', array( $this, 'send_email' ), 10, 2 );
    }

    public function check_thresholds( $metrics ) {
        if ( ! isset( $metrics['clicks'] ) || ! is_numeric( $metrics['clicks'] ) ) {
            return;
        }

        $clicks    = intval( $metrics['clicks'] );
        $threshold = intval( get_option( 'keywordinsight_click_threshold', 100 ) );

        if ( $clicks < $threshold ) {
            $this->maybe_schedule_email( $clicks, $threshold );
            set_transient(
                'keywordinsight_clicks_below_threshold_notice',
                array(
                    'clicks'    => $clicks,
                    'threshold' => $threshold,
                ),
                HOUR_IN_SECONDS
            );
        }
    }

    protected function maybe_schedule_email( $clicks, $threshold ) {
        if ( get_transient( 'keywordinsight_clicks_alert_sent' ) ) {
            return;
        }

        // Schedule email via WP-Cron to avoid blocking the metrics fetch
        if ( ! wp_next_scheduled( 'keywordinsight_send_clicks_alert_email', array( $clicks, $threshold ) ) ) {
            wp_schedule_single_event( time() + 60, 'keywordinsight_send_clicks_alert_email', array( $clicks, $threshold ) );
        }

        set_transient( 'keywordinsight_clicks_alert_sent', true, 12 * HOUR_IN_SECONDS );
    }

    public function send_email( $clicks, $threshold ) {
        $admin_email = get_option( 'admin_email' );
        if ( ! is_email( $admin_email ) ) {
            return;
        }

        $subject = __( 'KeywordInsight Pro: Clicks Threshold Alert', 'keywordinsight' );
        $message = sprintf(
            __( 'Your site recorded %d clicks, which is below the threshold of %d clicks.', 'keywordinsight' ),
            intval( $clicks ),
            intval( $threshold )
        );
        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

        wp_mail( $admin_email, $subject, $message, $headers );
    }

    public function display_admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $data = get_transient( 'keywordinsight_clicks_below_threshold_notice' );
        if ( empty( $data['clicks'] ) || empty( $data['threshold'] ) ) {
            return;
        }

        delete_transient( 'keywordinsight_clicks_below_threshold_notice' );

        $clicks    = intval( $data['clicks'] );
        $threshold = intval( $data['threshold'] );

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            esc_html( sprintf(
                __( 'KeywordInsight Pro: Recorded clicks (%d) fell below the threshold (%d).', 'keywordinsight' ),
                $clicks,
                $threshold
            ) )
        );
    }
}

PerformanceAlertNotifier::init();