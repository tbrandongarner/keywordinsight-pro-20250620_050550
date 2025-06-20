const HOOK = 'keywordinsightpro_recurring_job';

	/**
	 * Initialize hooks: cron schedules, event scheduling, and activation/deactivation.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', [ __CLASS__, 'addCronIntervals' ] );
		add_action( 'init', [ __CLASS__, 'scheduleCronJobs' ] );
		add_action( self::HOOK, [ __CLASS__, 'runRecurringJobs' ] );

		if ( defined( 'KEYWORDINSIGHT_PRO_MAIN_FILE' ) ) {
			register_activation_hook( KEYWORDINSIGHT_PRO_MAIN_FILE, [ __CLASS__, 'activation' ] );
			register_deactivation_hook( KEYWORDINSIGHT_PRO_MAIN_FILE, [ __CLASS__, 'deactivation' ] );
		}
	}

	/**
	 * Add custom cron interval schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public static function addCronIntervals( $schedules ) {
		$schedules['every_five_minutes'] = [
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'keywordinsightpro' ),
		];
		return $schedules;
	}

	/**
	 * Activation callback: schedules the recurring job if not already scheduled.
	 *
	 * @return void
	 */
	public static function activation() {
		self::scheduleCronJobs();
	}

	/**
	 * Deactivation callback: clears all scheduled occurrences of the recurring job hook.
	 *
	 * @return void
	 */
	public static function deactivation() {
		// Remove all scheduled events for this hook.
		if ( has_action( 'init', [ __CLASS__, 'scheduleCronJobs' ] ) ) {
			remove_action( 'init', [ __CLASS__, 'scheduleCronJobs' ] );
		}
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Schedule the recurring cron job if not already scheduled.
	 *
	 * @return void
	 */
	public static function scheduleCronJobs() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'every_five_minutes', self::HOOK );
		}
	}

	/**
	 * The callback executed on each cron interval.
	 *
	 * Invokes the metrics fetch function and logs or emails on error.
	 *
	 * @return void
	 */
	public static function runRecurringJobs() {
		if ( ! function_exists( 'keywordinsightpro_fetch_metrics' ) ) {
			return;
		}

		try {
			keywordinsightpro_fetch_metrics();
		} catch ( \Throwable $e ) {
			$message = sprintf(
				'KeywordInsightPro recurring job error: %s in %s on line %d. Stack trace: %s',
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				$e->getTraceAsString()
			);

			// Log error if debug logging is enabled, otherwise still log and notify admin.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( $message );
			} else {
				error_log( $message );
				$admin_email = get_option( 'admin_email' );
				if ( is_email( $admin_email ) ) {
					wp_mail( $admin_email, 'KeywordInsightPro Cron Error', $message );
				}
			}
		}
	}
}

RecurringJobScheduler::init();