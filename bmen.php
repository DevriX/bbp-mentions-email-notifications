<?php
/*
Plugin Name: bbPress Mentions Email Notifications
Plugin URI: https://samelh.com/
Description: Mentions Email Notifications for bbPress
Author: Samuel Elh
Version: 1.0.1
Author URI: https://samelh.com
*/

// prevent direct access
defined('ABSPATH') || exit('Direct access not allowed.' . PHP_EOL);

/**
  * bbPrss mentions plugin class
  */

class Bmen
{
	/** Class instance **/
    protected static $instance = null;

    /** plugin version **/
    public $version = '1.0.1';

    /** patterns to replace in mail **/
    public $patterns = null;

    /** default settings **/
    public $defaults = null;

    public function __construct()
    {
    	$this->patterns = array(
    		'[user-name]' => 'mentioned user name',
    		'[user-link]' => 'mentioned user profile link',
    		'[user-edit-profile-link]' => 'mentioned user profile edit link',
    		'[author-name]' => 'name of the user who mentions the target user (i.e topic/reply editor)',
    		'[post-title]' => 'topic/reply title',
    		'[post-link]' => 'topic or reply link',
    		'[post-content]' => 'topic or reply content text',
    		'[post-date]' => 'topic/reply publish date',
    		'[post-type]' => 'type: topic or reply',
    		'[post-ID]' => 'post ID',
    		'[site-name]' => 'site name',
    		'[site-login-link]' => 'login URL'
    	);
    	$this->defaults = array(
    		'email_subject' => '[user-name] has mentioned you on their [post-type] "[post-title]"',
    		'email_body' => "Dear [user-name],\n\n[author-name] has just mentioned you on their [post-type] \"[post-title]\":\n\n\"[post-content]\"\n\nRead this post on the forums: [post-link]\nTo update your preferences, please visit your profile edit page.",
    		'label' => 'Notify me whenever my name is mentioned on the forums'
    	);
    }

    /** Get Class instance **/
    public static function instance()
    {
        return null == self::$instance ? new self : self::$instance;
    }

    /** setup **/
    public static function init()
    {
    	// add profile edit field
    	add_action( "bbp_user_edit_after_contact", array( self::instance(), "peditField" ) );
    	// hook into profile edit update
    	add_action( "personal_options_update", array( self::instance(), "updateProfile" ) );
    	// hook into profile edit update: when updating other users' profiles
    	add_action( "edit_user_profile_update", array( self::instance(), "updateProfile" ) );
    	// notify mentioned users
    	add_action( "bbp_edit_topic_post_extras", array( self::instance(), "mentionsCheck" ) );
    	add_action( "bbp_edit_reply_post_extras", array( self::instance(), "mentionsCheck" ) );
    	add_action( "bbp_new_topic_post_extras", array( self::instance(), "mentionsCheck" ) );
    	add_action( "bbp_new_reply_post_extras", array( self::instance(), "mentionsCheck" ) );
    }

    /** settings **/
    public static function settings($skip_globals=null)
    {
    	global $bmen_settings;
    	if ( isset( $bmen_settings ) && !$skip_globals ) {
    		return $bmen_settings;
    	}
    	$custom = array();
    	// get custom settings
    	$meta = get_option( "bmen_settings" );
    	
    	if ( $meta && is_array( $meta ) ) {
    		// bbp edit profile label
    		if ( !empty( $meta['label'] ) ) {
    			$custom['label'] = esc_attr( $meta['label'] );
    		}
    		// email subject
    		if ( !empty( $meta['email_subject'] ) ) {
    			$custom['email_subject'] = esc_attr( $meta['email_subject'] );
    		}
    		// email body
    		if ( !empty( $meta['email_body'] ) ) {
    			$custom['email_body'] = esc_attr( $meta['email_body'] );
    		}
    	}
    	// pluggable
    	$bmen_settings = apply_filters(
    		'bmen_settings',
    		wp_parse_args( $custom, self::instance()->defaults ),
    		$custom
    	);
    	return $bmen_settings;
    }

    /** setup admin **/
    public static function adminInit()
    {
    	// setup admin menu
    	add_action( "admin_menu", array( self::instance(), "adminMenu" ) );
    	// target settings page
    	if ( isset( $_GET['page'] ) && 'bmen' === $_GET['page'] ) {
    		// add CSS
    		add_action( "admin_head", array( self::instance(), "printCSS" ) );
	    	// manage head
    		add_action( "admin_init", array( self::instance(), "settingsHead" ) );
    	}
        // add plugins.php meta links
        add_filter( "plugin_action_links_" . plugin_basename(__FILE__), array( self::instance(), "pushMeta" ) );
    }

    /** admin menu and settings page **/
    public static function adminMenu()
    {
    	// settings page
        add_options_page( 'bbPress Mentions Email Notifications', 'bbP mentions', 'manage_options', 'bmen', array( self::instance(), 'adminScreen' ) );
        // about page
        add_submenu_page(
            null,
            'About &lsaquo; bbPress Mentions Email Notifications',
            null,
            'manage_options',
            'bmen-about',
            array(self::instance(), "aboutScreen")
        );
    }

    public static function settingsHead()
    {
    	if ( isset( $_POST['submit'] ) ) {
    		if ( !isset( $_POST['bmen_nonce'] ) || !wp_verify_nonce( $_POST['bmen_nonce'], 'bmen_nonce' ) ) {
    			printf(
    				'<div class="%s notice is-dismissible"><p>%s</p></div>',
    				'error',
    				'ERROR: authentication failed.'
    			);
    			return;
    		}
    		if ( empty( $_POST['b'] ) ) return;
	    	$meta = get_option( "bmen_settings" );
	    	if ( !$meta || !is_array( $meta ) ) {
	    		$meta = array();
	    	}
    		$post = $_POST['b'];
    		if ( !empty( $post['m']['s'] ) && trim($post['m']['s']) ) {
    			$meta['email_subject'] = esc_attr( sanitize_text_field( $post['m']['s'] ) );
    		} else {
    			unset( $meta['email_subject'] );
    		}
    		if ( !empty( $post['m']['b'] ) && trim($post['m']['b']) ) {
    			$meta['email_body'] = esc_attr( implode( "\n", array_map( 'sanitize_text_field', explode( "\n", $post['m']['b'] ) ) ) );
    		} else {
    			unset( $meta['email_body'] );
    		}
    		if ( !empty( $post['l'] ) && trim($post['l']) ) {
    			$meta['label'] = esc_attr( sanitize_text_field( $post['l'] ) );
    		} else {
    			unset( $meta['label'] );
    		}
    		// little feedback
    		printf(
				'<div class="%s notice is-dismissible"><p>%s</p></div>',
				'updated',
				'Settings saved successfully!'
			);
    		if ( $meta ) {
	    		return update_option( "bmen_settings", apply_filters( "bmen_settings_meta", $meta ) );
	    	} else {
	    		return delete_option( "bmen_settings" );
	    	}
    	}
    }

    /** admin settings page callback **/
    public static function adminScreen()
    {
    	// get settings
    	$settings = self::settings(1);

    	?>			

	    	<div class="wrap">
	
                <h2>bbPress Mentions Email Notifications &rsaquo; Settings</h2>
                
                <?php self::topMenu(); ?>

				<form method="post">
		    		
			    	<div class="section">

			    		<h3>Email Settings</h3>

			    		<h4>Subject</h4>

			    		<p>
			    			<label><input type="text" name="b[m][s]" size="60" value="<?php echo wp_unslash($settings['email_subject']); ?>" /><br/>
			    			<em>Enter a subject-line for the email</em></label>
			    		</p>

			    		<h4>Body</h4>

			    		<p>
			    			<label>
			    				<textarea name="b[m][b]" rows="5" cols="62" id="bmen-mb"><?php echo wp_unslash($settings['email_body']); ?></textarea><br/>
				    			<em>Enter a body for the email</em>
			    			</label>
			    		</p>

			    		<p><em>You can format the subject and email body with the following patterns:</em></p>

			    		<?php foreach ( self::instance()->patterns as $p => $d ) : ?>
			    			<code><?php echo $p; ?></code>: <?php echo $d; ?><br/>
			    		<?php endforeach; ?>

			    	</div>

			    	<p></p>

			    	<div class="section">

			    		<h3>Profile-edit label</h3>

			    		<p>
			    			<label><input type="text" name="b[l]" size="60" value="<?php echo wp_unslash($settings['label']); ?>" /><br/>
			    			<em>Enter a text for the label</em></label>
			    		</p>

			    	</div>

			    	<p></p>

			    	<?php wp_nonce_field('bmen_nonce','bmen_nonce'); ?>
		    		<?php submit_button(); ?>

		    	</form>
	    	
	    	</div>
    	<?php
    }

    /** about screen **/
    public static function aboutScreen()
    {
        ?>
        <div class="wrap">
            <h2>bbPress Mentions Email Notifications &rsaquo; About</h2>
            <?php self::topMenu(); ?>
            <p style="font-weight:600">Thank you for using <a href="https://wordpress.org/plugins/bbp-mentions-email-notifications/">bbPress Mentions Email Notifications</a>, ver. <?php echo self::instance()->version; ?>!</p>
            <li><a href="https://wordpress.org/support/plugin/bbp-mentions-email-notifications">Support</li>
            <li><a href="https://wordpress.org/support/plugin/bbp-mentions-email-notifications/reviews/">Rate this plugin</a></li>
            <p style="font-weight:600">More bbPress plugins by Samuel Elh:</p>
            <li><a href="https://go.samelh.com/get/bbpress-messages/">bbPress Messages</a>: Add private messaging functionality to your WordPress forums</li>
            <li><a href="https://go.samelh.com/get/bbpress-ultimate/">bbPress Ultimate</a>: Add more user info to your forums/profiles, e.g online status, user country, social profiles and more..</li>
            <li><a href="https://go.samelh.com/get/bbpress-thread-prefixes/">bbPress Thread Prefixes</a>: Easily generate prefixes for topics and assign groups of prefixes for each forum..</li>
            <p style="font-weight:600">Subscribe for more!</p>
            <p>We have upcoming bbPress projects that we are very excited to work on. <a href="https://go.samelh.com/newsletter/">Subscribe to our newsletter</a> to get them first!</p>    
            <p style="font-weight:600">Need a custom bbPress plugin? <a href="https://samelh.com/work-with-me/">Hire me!</a></p>
        </div>
        <script type="text/javascript">(function(){var a=document.querySelector('#adminmenu a[href*="options-general.php?page=bmen"]');null!==a&&(a.parentNode.className="current")})();</script>
        <?php
    }

    /** top menu **/
    public static function topMenu()
    {
        if ( empty( $_GET['page'] ) ) return;
        $p = esc_attr($_GET['page']);
        ?>
            <h2 class="nav-tab-wrapper">

                <a class="nav-tab<?php echo"bmen"==$p?" nav-tab-active":"";?>" href="options-general.php?page=bmen">
                    <span>Settings</span>
                </a>

                <a class="nav-tab<?php echo"bmen-about"==$p?" nav-tab-active":"";?>" href="options-general.php?page=bmen-about">
                    <span>About</span>
                </a>
            </h2>
            <p></p>
        <?php
    }

    /** print CSS for settings page **/
    public static function printCSS()
    {
    	print('<style type="text/css">');
    	print('.wrap .section{display: block; background: #fff; padding: 1em; padding-top: 0.5em; border: 1px solid #dcdbdb;}');
    	print('#bmen-mb{background-color: #fff;display: inline-block;font-family: Consolas,Monaco,monospace;max-width: 100%;}');
    	print('</style>' . PHP_EOL);
    }

    /** push plugins.php urls **/
    public static function pushMeta( $links )
    {
        return array(
            '<a href="' . esc_url( 'options-general.php?page=bmen' ) . '">' . __( 'Settings' ) . '</a>',
            '<a href="' . esc_url( 'options-general.php?page=bmen-about' ) . '">' . __( 'About' ) . '</a>'
        ) + $links;
    }

    /** user preferences **/
    public static function canNotify( $user_id )
    {
    	$allow = !((bool) get_user_meta( $user_id, 'bmen_mute', 1 ));
    	return apply_filters( "bmen_can_notify", $allow, $user_id );
    }

    /** add field to bbp edit **/
    public static function peditField()
    {
		?>
			<div>
				<label for=""><?php echo apply_filters( 'bmen_pedit_field_header', "Email notifications" ); ?></label>	
				<label>
					<input type="checkbox" name="bmen_notify" style="width: auto;" <?php checked( self::canNotify( bbp_get_displayed_user_field('ID') ) ); ?> /> <?php echo self::settings()['label']; ?>
				</label>
			</div>
		<?php
	}

	/** hook into profile update **/
	public static function updateProfile( $user_id )
	{
		// exclude profile.php/user-edit update
		if ( is_admin() ) return;
		// update preference
		if ( isset( $_POST['bmen_notify'] ) ) {
			return delete_user_meta( $user_id, "bmen_mute" );
		} else {
			return update_user_meta( $user_id, "bmen_mute", time() );
		}
	}

	/** hook into posts to notify **/
	public static function mentionsCheck( $post_id )
	{
		if ( !function_exists('bbp_find_mentions') ) return;
		$post = get_post( $post_id );
		$mentions = bbp_find_mentions( $post->post_content );
		if ( !$mentions || !$post->ID ) return;
		// get previous notified users (to avoid notifying more than once)
		$notified = get_post_meta( $post->ID, "bmen_notified", 1 );
		if ( !$notified || !is_array( $notified ) ) {
			$notified = array();
		}
		foreach ( $mentions as $slug ) {
			// get mentioned user data
			$user = get_user_by( 'slug', $slug );
			// exclude false mentions
			if ( $user->ID ) {
				// preference check
				if ( !self::canNotify( $user->ID ) ) {
					continue;
				}
				// notified before
				if ( $notified && in_array($user->ID, $notified) ) {
					continue;
				}				
				// notify and push into meta
				if ( self::notify( $user, $post ) ) {
					$notified[] = $user->ID;
                    // trigger hook
                    do_action( "bmen_post_notify_user", $user, $post );
				}
			}
		}
		// push notified users to meta
		return update_post_meta( $post->ID, "bmen_notified", $notified );
	}

	/** process notifications **/
	public static function notify( $user, $post ) {
		if ( !isset( $user->ID ) && is_numeric( $user ) ) {
			$user = get_userdata( $user );
		}
		// check user
		if ( !$user->ID ) return;

		if ( !isset( $post->ID ) && is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		// check post
		if ( !$post->ID ) return;

		// author data
		$author = get_userdata( $post->post_author );

		// get settings
		$settings = self::settings();

		// pattern replace data
		$patternData = self::instance()->patterns;
		$patternData['[user-name]'] = $user->display_name;
		$patternData['[user-link]'] = bbp_get_user_profile_url( $user->ID );
		$patternData['[user-edit-profile-link]'] = bbp_get_user_profile_url( $user->ID ) . 'edit/';
		$patternData['[author-name]'] = $author->display_name;
		$patternData['[post-title]'] = apply_filters( "the_title", $post->post_title, $post->ID );
		$patternData['[post-link]'] = 'topic' !== $post->post_type ? bbp_get_reply_url( $post_id ) : get_the_permalink($post_id);
		$patternData['[post-content]'] = trim( $post->post_content );
		$patternData['[post-date]'] = $post->post_date;
		$patternData['[post-type]'] = $post->post_type;
		$patternData['[post-ID]'] = $post->ID;
		$patternData['[site-name]'] = get_bloginfo('name');
		$patternData['[site-login-link]'] = wp_login_url();

		$notification = array();
		//subject
		$notification['subject'] = str_replace(
			array_keys( $patternData ),
			$patternData,
			$settings['email_subject']
		);
		// body
		$notification['body'] = str_replace(
			array_keys( $patternData ),
			$patternData,
			$settings['email_body']
		);
		// email
		$notification['email'] = $user->user_email;
		// pluggable
		$notification = apply_filters( "bmen_notification", $notification, $user, $post, $patternData );
		// trigger hook
		do_action( "bmen_pre_mail", $notification, $user, $post, $patternData );
		// send the mail
		return (bool) wp_mail(
			$notification['email'],
			$notification['subject'],
			$notification['body']
		);
	}
}

// init plugin
Bmen::init();

if ( is_admin() ) {
	// init admin
	Bmen::adminInit();
}