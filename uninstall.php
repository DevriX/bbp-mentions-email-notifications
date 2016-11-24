<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
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

$umeta = array(
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

// flush user meta
foreach ( get_users() as $user ) {
    foreach ( $umeta as $key ) {
        delete_user_meta( $user->ID, $key );
    }
}

// flush post meta
foreach ( get_posts( array( 'post_type' => array( 'topic', 'reply' ), 'numberposts' => -1 ) ) as $post ) {
    foreach ( $postmeta as $key ) {
        delete_post_meta( $post->ID, $key );
    }
}