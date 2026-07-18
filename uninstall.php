<?php
/**
 * Removes plugin-owned reports on uninstall.
 *
 * @package PressCareAIErrorDoctor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_metadata( 'user', 0, '_pcaied_last_report', '', true );
delete_metadata( 'user', 0, '_pcaied_last_ai_report', '', true );
delete_metadata( 'user', 0, '_pcaied_handled_findings', '', true );
