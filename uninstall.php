<?php
/* if uninstall.php is not called by WordPress, die */

defined('ABSPATH') || die('Direct access not allowed.' . PHP_EOL);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die('Direct access not allowed.' . PHP_EOL);
}

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die('Direct access not allowed.' . PHP_EOL);
}

$options = array(
	'bbpen_status',
	'bbpen_format',
	'bbpen_subject',
	'bbpen_headers_from',
	'bbpen_headers_from_email',
	'bbpen_disable_on_edit',
	'bbpen_pref_label',
    'bmen_settings'
);

$usermeta = array(
    'bbpen_dont_email_me',
    'bbpen_notified_in',
    'bmen_mute'
);

$postmeta = array(
    'bmen_notified',
);

// flush options
foreach ( $options as $option ) {
	delete_option( $option );
}

global $wpdb;

// flush user meta
$wpdb->query(sprintf(
    "DELETE FROM {$wpdb->usermeta} WHERE `meta_key` IN ('%s')",
    implode("','", $usermeta)
));

// flush post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE `meta_key` = 'bmen_notified'");