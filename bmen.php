<?php
/*
Plugin Name: bbPress Mentions Email Notifications
Plugin URI: https://samelh.com/
Description: Mentions Email Notifications for bbPress
Author: Samuel Elh
Version: 1.0.3
Author URI: https://samelh.com
*/

// prevent direct access
defined('ABSPATH') || exit('Direct access not allowed.' . PHP_EOL);

/**
  * bbPrss mentions plugin class
  */

class bbpMentionsEmailNotification
{
    /** plugin version **/
    public $version;

    /** patterns to replace in mail **/
    public $patterns;

    /** default settings **/
    public $defaults;

    // text domain
    public $text_domain;

    // admin feedback
    public $feedback;

    public function __construct()
    {
    	$this->patterns = array(
    		'[user-name]' => __('mentioned user name', $this->text_domain),
    		'[user-link]' => __('mentioned user profile link', $this->text_domain),
    		'[user-edit-profile-link]' => __('mentioned user profile edit link', $this->text_domain),
    		'[author-name]' => __('name of the user who mentions the target user (i.e topic/reply editor)', $this->text_domain),
    		'[post-title]' => __('topic/reply title', $this->text_domain),
    		'[post-link]' => __('topic or reply link', $this->text_domain),
    		'[post-content]' => __('topic or reply content text', $this->text_domain),
    		'[post-date]' => __('topic/reply publish date', $this->text_domain),
    		'[post-type]' => __('type: topic or reply', $this->text_domain),
    		'[post-ID]' => __('post ID', $this->text_domain),
    		'[site-name]' => __('site name', $this->text_domain),
    		'[site-login-link]' => __('login URL', $this->text_domain)
    	);

    	$this->defaults = array(
    		'email_subject' => __('[user-name] has mentioned you on their [post-type] "[post-title]"', $this->text_domain),
    		'email_body' => __("Dear [user-name],\n\n[author-name] has just mentioned you on their [post-type] \"[post-title]\":\n\n\"[post-content]\"\n\nRead this post on the forums: [post-link]\nTo update your preferences, please visit your profile edit page.", $this->text_domain),
    		'label' => __('Notify me whenever my name is mentioned on the forums', $this->text_domain)
    	);

        $this->text_domain = 'bbp-mentions-email-notifications';
        
        $this->version = '1.0.3';
    }

    /** setup **/
    public function init()
    {
        // load i18n
        load_plugin_textdomain($this->text_domain, false, dirname(plugin_basename(__FILE__)).'/languages');
    	// add profile edit field
    	add_action('bbp_user_edit_after_contact', array($this, 'peditField'));
    	// hook into profile edit update
    	add_action('personal_options_update', array($this, 'updateProfile'));
    	// hook into profile edit update: when updating other users' profiles
    	add_action('edit_user_profile_update', array($this, 'updateProfile'));
    	// notify mentioned users
    	add_action('bbp_edit_topic_post_extras', array($this, 'mentionsCheck'));
    	add_action('bbp_edit_reply_post_extras', array($this, 'mentionsCheck'));
    	add_action('bbp_new_topic_post_extras', array($this, 'mentionsCheck'));
    	add_action('bbp_new_reply_post_extras', array($this, 'mentionsCheck'));
        // admin
        if ( is_admin() ) {
            // init
            $this->adminInit();
        }
    }

    /** settings **/
    public function settings()
    {
    	global $bmen_settings;

    	if ( isset($bmen_settings) ) {
    		return $bmen_settings;
    	}

        $bmen_settings = wp_parse_args((array) get_option('bmen_settings', null), $this->defaults);
        $bmen_settings = apply_filters('bmen_settings', $bmen_settings);
    	
    	return $bmen_settings;
    }

    /** setup admin **/
    public function adminInit()
    {
    	// setup admin menu
    	add_action('admin_menu', array($this, 'adminMenu'));
    	// target settings page
    	if ( isset($_GET['page']) && 'bmen' === $_GET['page'] ) {
    		// add CSS
    		add_action('admin_head', array($this, 'printCSS'));
	    	// manage head
    		add_action('admin_init', array($this, 'updateSettings'));
    	}
        // add plugins.php meta links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'pushMeta'));
    }

    /** admin menu and settings page **/
    public function adminMenu()
    {
    	// settings page
        add_options_page(
            __('bbPress Mentions Email Notifications', $this->text_domain),
            __('bbP mentions', $this->text_domain),
            'manage_options',
            'bmen',
            array($this, 'adminScreen')
        );
        // about page
        add_submenu_page(
            null,
            __('About &lsaquo; bbPress Mentions Email Notifications', $this->text_domain),
            null,
            'manage_options',
            'bmen-about',
            array($this, 'aboutScreen')
        );
    }

    public function updateSettings()
    {
    	if ( !isset($_POST['submit']) )
            return;

		if ( !isset( $_POST['bmen_nonce'] ) || !wp_verify_nonce( $_POST['bmen_nonce'], 'bmen_nonce' ) ) {
            $this->feedback = sprintf(
                '<div class="error notice is-dismissible"><p>%s</p></div>',
                __('ERROR: authentication failed.', $this->text_domain)
            );
			return;
		}

        global $bmen_settings;
        $opt = array();

		if ( !empty( $_POST['subject'] ) && trim($_POST['subject']) ) {
			$opt['email_subject'] = esc_attr( $_POST['subject'] );
		}

		if ( !empty( $_POST['body'] ) && trim($_POST['body']) ) {
			$opt['email_body'] = esc_attr($_POST['body']);
		}

		if ( !empty( $_POST['label'] ) && trim($_POST['label']) ) {
			$opt['label'] = esc_attr($_POST['label']);
		}

		if ( $opt ) {
    		update_option('bmen_settings', apply_filters('bmen_settings_meta', $opt));
            // little feedback
            $this->feedback = sprintf(
                '<div class="updated notice is-dismissible"><p>%s</p></div>',
                __('Settings updated successfully!', $this->text_domain)
            );
    	} else {
    		delete_option('bmen_settings');
            // little feedback
            $this->feedback = sprintf(
                '<div class="updated notice is-dismissible"><p>%s</p></div>',
                __('Settings flushed successfully!', $this->text_domain)
            );
    	}

        // update global obj
        $bmen_settings = wp_parse_args($opt, $this->defaults);
    }

    /** admin settings page callback **/
    public function adminScreen()
    {
    	// get settings
    	$opt = $this->settings();
    	?>			

	    	<div class="wrap">
	
                <h2><?php _e('bbPress Mentions Email Notifications &rsaquo; Settings', $this->text_domain); ?></h2>
                
                <?php $this->topMenu(); ?>

				<form method="post">
		    		
			    	<div class="section">

			    		<h3><?php _e('Email Settings', $this->text_domain); ?></h3>

			    		<h4><?php _e('Subject', $this->text_domain); ?></h4>

			    		<p>
			    			<label><input type="text" name="subject" size="60" value="<?php echo wp_unslash($opt['email_subject']); ?>" /><br/>
			    			<em><?php _e('Enter a subject-line for the email', $this->text_domain); ?></em></label>
			    		</p>

			    		<h4><?php _e('Body', $this->text_domain); ?></h4>

			    		<p>
			    			<label>
			    				<textarea name="body" rows="5" cols="62" id="bmen-mb"><?php echo wp_unslash($opt['email_body']); ?></textarea><br/>
				    			<em><?php _e('Enter a body for the email', $this->text_domain); ?></em>
			    			</label>
			    		</p>

			    		<p><em><?php _e('You can format the subject and email body with the following patterns:', $this->text_domain); ?></em></p>

			    		<?php foreach ($this->patterns as $p => $d) : ?>
			    			<code><?php echo $p; ?></code>: <?php echo $d; ?><br/>
			    		<?php endforeach; ?>

			    	</div>

			    	<p></p>

			    	<div class="section">

			    		<h3><?php _e('Profile-edit label', $this->text_domain); ?></h3>

			    		<p>
			    			<label><input type="text" name="label" size="60" value="<?php echo wp_unslash($opt['label']); ?>" /><br/>
			    			<em><?php _e('Enter a text for the label', $this->text_domain); ?></em></label>
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
    public function aboutScreen()
    {
        ?>
        <div class="wrap">
            <h2><?php _e('bbPress Mentions Email Notifications &rsaquo; About', $this->text_domain); ?></h2>
            <?php $this->topMenu(); ?>
            <p style="font-weight:600">Thank you for using <a href="https://wordpress.org/plugins/bbp-mentions-email-notifications/">bbPress Mentions Email Notifications</a>, ver. <?php echo $this->version; ?>!</p>
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
    public function topMenu()
    {
        if ( $this->feedback && trim($this->feedback) ) {
            print $this->feedback;
        }
        $page = isset($_GET['page']) ? $_GET['page'] : null;
        ?>
            <h2 class="nav-tab-wrapper">
                <a class="nav-tab<?php echo"bmen"==$page?" nav-tab-active":"";?>" href="options-general.php?page=bmen">
                    <span><?php _e('Settings', $this->text_domain); ?></span>
                </a>

                <a class="nav-tab<?php echo"bmen-about"==$page?" nav-tab-active":"";?>" href="options-general.php?page=bmen-about">
                    <span><?php _e('About', $this->text_domain); ?></span>
                </a>
            </h2>
            <p></p>
        <?php
    }

    /** print CSS for settings page **/
    public function printCSS()
    {
    	print('<style type="text/css">');
    	print('.wrap .section{display: block; background: #fff; padding: 1em; padding-top: 0.5em; border: 1px solid #dcdbdb;}');
    	print('#bmen-mb{background-color: #fff;display: inline-block;font-family: Consolas,Monaco,monospace;max-width: 100%;}');
    	print('</style>' . PHP_EOL);
    }

    /** push plugins.php urls **/
    public function pushMeta( $links )
    {
        return array(
            '<a href="' . esc_url('options-general.php?page=bmen') . '">' . __('Settings', $this->text_domain) . '</a>',
            '<a href="' . esc_url('options-general.php?page=bmen-about') . '">' . __('About', $this->text_domain) . '</a>'
        ) + $links;
    }

    /** user preferences **/
    public function canNotify( $user_id )
    {
    	$allow = !get_user_meta($user_id, 'bmen_mute', 1);
    	return apply_filters( "bmen_can_notify", $allow, $user_id );
    }

    /** add field to bbp edit **/
    public function peditField()
    {
        $opt = $this->settings();
		?>
			<div>
				<label for=""><?php echo apply_filters('bmen_pedit_field_header', __('Email notifications', $this->text_domain)); ?></label>	
				<label>
					<input type="checkbox" name="bmen_notify" style="width: auto;" <?php checked( $this->canNotify( bbp_get_displayed_user_field('ID') ) ); ?> /> <?php echo $opt['label']; ?>
				</label>
			</div>
		<?php
	}

	/** hook into profile update **/
	public function updateProfile( $user_id )
	{
		// exclude profile.php/user-edit update
		if ( is_admin() ) return;
		// update preference
		if ( isset($_POST['bmen_notify']) ) {
			return delete_user_meta($user_id, 'bmen_mute');
		} else {
			return update_user_meta($user_id, 'bmen_mute', time());
		}
	}

	/** hook into posts to notify **/
	public function mentionsCheck( $post_id )
	{
		if ( !function_exists('bbp_find_mentions') ) return;
		$post = get_post( $post_id );
		$mentions = bbp_find_mentions( $post->post_content );
		if ( !$mentions || !$post->ID ) return;
		// get previous notified users (to avoid notifying more than once)
		$notified = get_post_meta($post->ID, 'bmen_notified', 1);
		if ( !$notified || !is_array( $notified ) ) {
			$notified = array();
		}
		foreach ( $mentions as $slug ) {
			// get mentioned user data
			$user = get_user_by( 'slug', $slug );
			// exclude false mentions
			if ( $user->ID ) {
				// preference check
				if ( !$this->canNotify( $user->ID ) ) {
					continue;
				}
				// notified before
				if ( $notified && in_array($user->ID, $notified) ) {
					continue;
				}				
				// notify and push into meta
				if ( $this->notify( $user, $post ) ) {
					$notified[] = $user->ID;
                    // trigger hook
                    do_action('bmen_post_notify_user', $user, $post );
				}
			}
		}
		// push notified users to meta
		return update_post_meta($post->ID, 'bmen_notified', $notified);
	}

	/** process notifications **/
	public function notify( $user, $post ) {
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
		$settings = $this->settings();

		// pattern replace data
		$patternData = $this->patterns;
		$patternData['[user-name]'] = $user->display_name;
		$patternData['[user-link]'] = bbp_get_user_profile_url( $user->ID );
		$patternData['[user-edit-profile-link]'] = bbp_get_user_profile_url( $user->ID ) . 'edit/';
		$patternData['[author-name]'] = $author->display_name;
		$patternData['[post-title]'] = apply_filters( "the_title", $post->post_title, $post->ID );
        $patternData['[post-link]'] = bbp_get_reply_url( bbp_get_reply_id( $post->ID ) );
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
			array_keys($patternData),
			$patternData,
			$settings['email_body']
		);
		// email
		$notification['email'] = $user->user_email;
        // headers
        $notification['headers'] = '';
        // html formatted emails
        if ( strip_tags($notification['body']) !== $notification['body'] ) {
            $notification['headers'] = array('Content-Type: text/html; charset=' . get_option('blog_charset'));
        }
		// pluggable
		$notification = apply_filters('bmen_notification', $notification, $user, $post, $patternData );
		// trigger hook
		do_action('bmen_pre_mail', $notification, $user, $post, $patternData );
		// send the mail
		return (bool) wp_mail(
			$notification['email'],
			$notification['subject'],
			$notification['body'],
            $notification['headers']
		);
	}
}

$bbpMentionsEmailNotification = new bbpMentionsEmailNotification;

// init plugin
add_action('plugins_loaded', array($bbpMentionsEmailNotification, 'init'));