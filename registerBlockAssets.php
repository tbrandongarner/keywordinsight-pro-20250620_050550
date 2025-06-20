<?php
defined( 'ABSPATH' ) || exit;

$block_file = __DIR__ . '/Blocks/KeywordInsightsBlock.php';
if ( file_exists( $block_file ) ) {
	require_once $block_file;
	if ( class_exists( 'KeywordInsightPro\\Blocks\\KeywordInsightsBlock' ) && method_exists( 'KeywordInsightPro\\Blocks\\KeywordInsightsBlock', 'register' ) ) {
		add_action( 'init', [ 'KeywordInsightPro\\Blocks\\KeywordInsightsBlock', 'register' ] );
	}
}