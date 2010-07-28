<?php
/*
Plugin Name: WP-reCAPTCHA
Plugin URI: http://www.blaenkdenum.com/wp-recaptcha/
Description: Integrates reCAPTCHA anti-spam solutions with wordpress
Version: 2.9.6
Author: Jorge Peña
Email: support@recaptcha.net
Author URI: http://www.blaenkdenum.com
*/

// Plugin was initially created by Ben Maurer and Mike Crawford
// Permissions/2.5 transition help from Jeremy Clarke @ http://globalvoicesonline.org

// WORDPRESS MU DETECTION

// WordPress MU settings - DON'T EDIT
//    0 - Regular WordPress installation
//    1 - WordPress MU Forced Activated
//    2 - WordPress MU Optional Activation

$wpmu = 0;

if(is_dir(WP_CONTENT_DIR . '/mu-plugins')){
	if (is_file(WP_CONTENT_DIR . '/mu-plugins/wp-recaptcha.php')) // forced activated
	   $wpmu = 1;
	else if (is_file(WP_CONTENT_DIR . '/plugins/wp-recaptcha.php')) // optionally activated
	   $wpmu = 2;
}

if ($wpmu == 1)
   $recaptcha_opt = get_site_option('recaptcha'); // get the options from the database
else
   $recaptcha_opt = get_option('recaptcha'); // get the options from the database

// END WORDPRESS MU DETECTION
   
if ($wpmu == 1)
   require_once(WP_CONTENT_DIR . '/mu-plugins/wp-recaptcha/recaptchalib.php');
else
   require_once(WP_CONTENT_DIR . '/plugins/recaptchalib.php');

// doesn't need to be secret, just shouldn't be used by any other code.
define ("RECAPTCHA_WP_HASH_SALT", "b7e0638d85f5d7f3694f68e944136d62");

/* =============================================================================
   CSS - This links the pages to the stylesheet to be properly styled
   ============================================================================= */

function re_css() {
   global $recaptcha_opt, $wpmu;
   
   if (!defined('WP_CONTENT_URL'))
      define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
   
   $path = WP_CONTENT_URL . '/plugins/wp-recaptcha/recaptcha.css';
   
   if ($wpmu == 1)
		$path = WP_CONTENT_URL . '/mu-plugins/wp-recaptcha/recaptcha.css';
   
   echo '<link rel="stylesheet" type="text/css" href="' . $path . '" />';
}

add_action('wp_head', 're_css'); // include the stylesheet in typical pages to style hidden emails
add_action('admin_head', 're_css'); // include stylesheet to style options page

/* =============================================================================
   End CSS
   ============================================================================= */

// If the plugin is deactivated, delete the preferences
function delete_preferences() {
   global $wpmu;

   if ($wpmu != 1)
		delete_option('recaptcha');
}

register_deactivation_hook(__FILE__, 'delete_preferences');

/* =============================================================================
   reCAPTCHA Plugin Default Options
   ============================================================================= */

$option_defaults = array (
   're_bypass' => '', // whether to sometimes skip reCAPTCHAs for registered users
   're_bypasslevel' => '', // who doesn't have to do the reCAPTCHA (should be a valid WordPress capability slug)
   're_theme' => 'red', // the default theme for reCAPTCHA on the comment post
   're_lang' => 'en', // the default language for reCAPTCHA
   're_tabindex' => '5', // the default tabindex for reCAPTCHA
   're_comments' => '1', // whether or not to show reCAPTCHA on the comment post
   're_xhtml' => '0', // whether or not to be XHTML 1.0 Strict compliant
   'error_blank' => '<strong>ERROR</strong>: Please fill in the reCAPTCHA form.', // the message to display when the user enters no CAPTCHA response
   'error_incorrect' => '<strong>ERROR</strong>: That reCAPTCHA response was incorrect.', // the message to display when the user enters the incorrect CAPTCHA response
);

// install the defaults
if ($wpmu != 1)
   add_option('recaptcha', $option_defaults, 'reCAPTCHA Default Options', 'yes');

/* =============================================================================
   End reCAPTCHA Plugin Default Options
   ============================================================================= */

/* =============================================================================
   reCAPTCHA - The reCAPTCHA comment spam protection section
   ============================================================================= */
function recaptcha_wp_hash_comment($id)
{
	global $recaptcha_opt;
   
	if (function_exists('wp_hash'))
		return wp_hash(RECAPTCHA_WP_HASH_COMMENT . $id);
	else
		return md5(RECAPTCHA_WP_HASH_COMMENT . $recaptcha_opt['privkey'] . $id);
}

function recaptcha_wp_get_html ($recaptcha_error, $use_ssl=false) {
	global $recaptcha_opt;
   
	return recaptcha_get_html($recaptcha_opt['pubkey'], $recaptcha_error, $use_ssl, $recaptcha_opt['re_xhtml']);
}

/**
 *  Embeds the reCAPTCHA widget into the comment form.
 * 
 */	
function recaptcha_comment_form() {
   global $user_ID, $recaptcha_opt;

   // set the minimum capability needed to skip the captcha if there is one
   if ($recaptcha_opt['re_bypass'] && $recaptcha_opt['re_bypasslevel'])
      $needed_capability = $recaptcha_opt['re_bypasslevel'];

	// skip the reCAPTCHA display if the minimum capability is met
	if (($needed_capability && current_user_can($needed_capability)) || !$recaptcha_opt['re_comments'])
		return;
   
   else {
		// Did the user fail to match the CAPTCHA? If so, let them know
		if ($_GET['rerror'] == 'incorrect-captcha-sol')
		echo "<p class=\"recaptcha-error\">" . $recaptcha_opt['error_incorrect'] . "</p>";
   
		//modify the comment form for the reCAPTCHA widget
		$recaptcha_js_opts = <<<OPTS
		<script type='text/javascript'>
				var RecaptchaOptions = { theme : '{$recaptcha_opt['re_theme']}', lang : '{$recaptcha_opt['re_lang']}' , tabindex : {$recaptcha_opt['re_tabindex']} };
		</script>
OPTS;

		if ($recaptcha_opt['re_xhtml']) {
		$comment_string = <<<COMMENT_FORM
				<div id="recaptcha-submit-btn-area"><br /></div>
				<script type='text/javascript'>
				var sub = document.getElementById('submit');
				sub.parentNode.removeChild(sub);
				document.getElementById('recaptcha-submit-btn-area').appendChild (sub);
				document.getElementById('submit').tabIndex = 6;
				if ( typeof _recaptcha_wordpress_savedcomment != 'undefined') {
						document.getElementById('comment').value = _recaptcha_wordpress_savedcomment;
				}
				document.getElementById('recaptcha_table').style.direction = 'ltr';
				</script>
COMMENT_FORM;
		}
		
		else {
		$comment_string = <<<COMMENT_FORM
				<div id="recaptcha-submit-btn-area"></div> 
				<script type='text/javascript'>
				var sub = document.getElementById('submit');
				sub.parentNode.removeChild(sub);
				document.getElementById('recaptcha-submit-btn-area').appendChild (sub);
				document.getElementById('submit').tabIndex = 6;
				if ( typeof _recaptcha_wordpress_savedcomment != 'undefined') {
						document.getElementById('comment').value = _recaptcha_wordpress_savedcomment;
				}
				document.getElementById('recaptcha_table').style.direction = 'ltr';
				</script>
				<noscript>
				 <style type='text/css'>#submit {display:none;}</style>
				 <input name="submit" type="submit" id="submit-alt" tabindex="6" value="Submit Comment"/> 
				</noscript>
COMMENT_FORM;
		}

		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
         $use_ssl = true;
		else
         $use_ssl = false;
		
		echo $recaptcha_js_opts .  recaptcha_wp_get_html($_GET['rerror'], $use_ssl) . $comment_string;
   }
}

add_action('comment_form', 'recaptcha_comment_form');

function recaptcha_wp_show_captcha_for_comment() {
   global $user_ID;
   return true;
}

$recaptcha_saved_error = '';

/**
 * Checks if the reCAPTCHA guess was correct and sets an error session variable if not
 * @param array $comment_data
 * @return array $comment_data
 */
function recaptcha_wp_check_comment($comment_data) {
	global $user_ID, $recaptcha_opt;
	global $recaptcha_saved_error;
   	
   // set the minimum capability needed to skip the captcha if there is one
   if ($recaptcha_opt['re_bypass'] && $recaptcha_opt['re_bypasslevel'])
      $needed_capability = $recaptcha_opt['re_bypasslevel'];
        
	// skip the filtering if the minimum capability is met
	if (($needed_capability && current_user_can($needed_capability)) || !$recaptcha_opt['re_comments'])
		return $comment_data;
   
	if (recaptcha_wp_show_captcha_for_comment()) {
		if ( $comment_data['comment_type'] == '' ) { // Do not check trackbacks/pingbacks
			$challenge = $_POST['recaptcha_challenge_field'];
			$response = $_POST['recaptcha_response_field'];

			$recaptcha_response = recaptcha_check_answer ($recaptcha_opt ['privkey'], $_SERVER['REMOTE_ADDR'], $challenge, $response);
			if ($recaptcha_response->is_valid)
				return $comment_data;
			else {
				$recaptcha_saved_error = $recaptcha_response->error;
				add_filter('pre_comment_approved', create_function('$a', 'return \'spam\';'));
				return $comment_data;
			}
		}
	}
	return $comment_data;
}

/*
 * If the reCAPTCHA guess was incorrect from recaptcha_wp_check_comment, then redirect back to the comment form 
 * @param string $location
 * @param OBJECT $comment
 * @return string $location
 */
function recaptcha_wp_relative_redirect($location, $comment) {
	global $recaptcha_saved_error;
	if($recaptcha_saved_error != '') { 
		//replace the '#comment-' chars on the end of $location with '#commentform'.

		$location = substr($location, 0,strrpos($location, '#')) .
			((strrpos($location, "?") === false) ? "?" : "&") .
			'rcommentid=' . $comment->comment_ID . 
			'&rerror=' . $recaptcha_saved_error .
			'&rchash=' . recaptcha_wp_hash_comment ($comment->comment_ID) . 
			'#commentform';
	}
	return $location;
}

/*
 * If the reCAPTCHA guess was incorrect from recaptcha_wp_check_comment, then insert their saved comment text
 * back in the comment form. 
 * @param boolean $approved
 * @return boolean $approved
 */
function recaptcha_wp_saved_comment() {
   if (!is_single() && !is_page())
      return;

   if ($_GET['rcommentid'] && $_GET['rchash'] == recaptcha_wp_hash_comment ($_GET['rcommentid'])) {
      $comment = get_comment($_GET['rcommentid']);

      $com = preg_replace('/([\\/\(\)\+\;\'\"])/e','\'%\'.dechex(ord(\'$1\'))', $comment->comment_content);
      $com = preg_replace('/\\r\\n/m', '\\\n', $com);

      echo "
      <script type='text/javascript'>
      var _recaptcha_wordpress_savedcomment =  '" . $com  ."';

      _recaptcha_wordpress_savedcomment = unescape(_recaptcha_wordpress_savedcomment);
      </script>
      ";

      wp_delete_comment($comment->comment_ID);
   }
}

function recaptcha_wp_blog_domain () {
	$uri = parse_url(get_settings('siteurl'));
	return $uri['host'];
}

add_filter('wp_head', 'recaptcha_wp_saved_comment',0);
add_filter('preprocess_comment', 'recaptcha_wp_check_comment',0);
add_filter('comment_post_redirect', 'recaptcha_wp_relative_redirect',0,2);

function recaptcha_wp_add_options_to_admin() {
   global $wpmu;

   if ($wpmu == 1 && is_site_admin()) {
		add_submenu_page('wpmu-admin.php', 'reCAPTCHA', 'reCAPTCHA', 'manage_options', __FILE__, 'recaptcha_wp_options_subpanel');
		add_options_page('reCAPTCHA', 'reCAPTCHA', 'manage_options', __FILE__, 'recaptcha_wp_options_subpanel');
   }
   else if ($wpmu != 1) {
		add_options_page('reCAPTCHA', 'reCAPTCHA', 'manage_options', __FILE__, 'recaptcha_wp_options_subpanel');
   }
}

function recaptcha_wp_options_subpanel() {
   global $wpmu;
	// Default values for the options array
	$optionarray_def = array(
		're_bypasslevel' => '3',
		'mh_bypasslevel' => '3',
		're_lang' => 'en',
		're_tabindex' => '5',
		're_comments' => '1',
		're_registration' => '1',
		're_xhtml' => '0',
      'mh_replace_link' => '',
      'mh_replace_title' => '',
      'error_blank' => '<strong>ERROR</strong>: Please fill in the reCAPTCHA form.',
      'error_incorrect' => '<strong>ERROR</strong>: That reCAPTCHA response was incorrect.',
		);

	if ($wpmu != 1)
		add_option('recaptcha', $optionarray_def, 'reCAPTCHA Options');

	/* Check form submission and update options if no error occurred */
	if (isset($_POST['submit'])) {
		$optionarray_update = array (
		're_bypass' => $_POST['re_bypass'],
		're_bypasslevel' => $_POST['re_bypasslevel'],
		're_theme' => $_POST['re_theme'],
		're_lang' => $_POST['re_lang'],
		're_tabindex' => $_POST['re_tabindex'],
		're_comments' => $_POST['re_comments'],
		're_xhtml' => $_POST['re_xhtml'],
    'error_blank' => $_POST['error_blank'],
    'error_incorrect' => $_POST['error_incorrect'],
		);
	// save updated options
	if ($wpmu == 1)
		update_site_option('recaptcha', $optionarray_update);
	else
		update_option('recaptcha', $optionarray_update);
}

	/* Get options */
	if ($wpmu == 1)
		$optionarray_def = get_site_option('recaptcha');
   else
		$optionarray_def = get_option('recaptcha');

/* =============================================================================
   reCAPTCHA Admin Page and Functions
   ============================================================================= */
   
/*
 * Display an HTML <select> listing the capability options for disabling security 
 * for registered users. 
 * @param string $select_name slug to use in <select> id and name
 * @param string $checked_value selected value for dropdown, slug form.
 * @return NULL
 */
 
function recaptcha_dropdown_capabilities($select_name, $checked_value="") {
	// define choices: Display text => permission slug
	$capability_choices = array (
	 	'All registered users' => 'read',
	 	'Edit posts' => 'edit_posts',
	 	'Publish Posts' => 'publish_posts',
	 	'Moderate Comments' => 'moderate_comments',
	 	'Administer site' => 'level_10'
	 	);
	// print the <select> and loop through <options>
	echo '<select name="' . $select_name . '" id="' . $select_name . '">' . "\n";
	foreach ($capability_choices as $text => $capability) :
		if ($capability == $checked_value) $checked = ' selected="selected" ';
		echo '\t <option value="' . $capability . '"' . $checked . ">$text</option> \n";
		$checked = NULL;
	endforeach;
	echo "</select> \n";
 } // end recaptcha_dropdown_capabilities()
   
?>

<!-- ############################## BEGIN: ADMIN OPTIONS ################### -->
<div class="wrap">
	<h2>reCAPTCHA Options</h2>
	<h3>About reCAPTCHA</h3>
	<p>reCAPTCHA is a free, accessible CAPTCHA service that helps to digitize books while blocking spam on your blog.</p>
	
	<p>reCAPTCHA asks commenters to retype two words scanned from a book to prove that they are a human. This verifies that they are not a spambot while also correcting the automatic scans of old books. So you get less spam, and the world gets accurately digitized books. Everybody wins! For details, visit the <a href="http://recaptcha.net/">reCAPTCHA website</a>.</p>
   <p><strong>NOTE</strong>: If you are using some form of Cache plugin you will probably need to flush/clear your cache for changes to take effect.</p>
   
	<form name="form1" method="post" action="<?php echo $_SERVER['REDIRECT_SCRIPT_URI'] . '?page=' . plugin_basename(__FILE__); ?>&updated=true">
		<div class="submit">
			<input type="submit" name="submit" value="<?php _e('Update Options') ?> &raquo;" />
		</div>
	
	<!-- ****************** Operands ****************** -->
   <table class="form-table">
  	<tr valign="top">
		<th scope="row">Comment Options</th>
		<td>
			<!-- Show reCAPTCHA on the comment post -->
			<big><input type="checkbox" name="re_comments" id="re_comments" value="1" <?php if($optionarray_def['re_comments'] == true){echo 'checked="checked"';} ?> /> <label for="re_comments">Enable reCAPTCHA for comments.</label></big>
			<br />
			<!-- Don't show reCAPTCHA to admins -->
			<div class="theme-select">
			<input type="checkbox" id="re_bypass" name="re_bypass" <?php if($optionarray_def['re_bypass'] == true){echo 'checked="checked"';} ?>/>
			<label name="re_bypass" for="re_bypass">Hide reCAPTCHA for <strong>registered</strong> users who can:</label>
			<?php recaptcha_dropdown_capabilities('re_bypasslevel', $optionarray_def['re_bypasslevel']); // <select> of capabilities ?>
			</div>

			<!-- Tab Index -->
			<label for="re_tabindex">Tab Index (<em>e.g. WP: <strong>5</strong>, WPMU: <strong>3</strong></em>):</label>
			<input name="re_tabindex" id="re_tabindex" size="5" value="<?php  echo $optionarray_def['re_tabindex']; ?>" />
			<br />
			<?php global $wpmu; if ($wpmu == 1 || $wpmu == 0) { ?>
		</td>
	</tr>
   <tr valign="top">
      <th scope="row">Error Messages</th>
         <td>
            <p>The following are the messages to display when the user does not enter a CAPTCHA response or enters the incorrect CAPTCHA response.</p>
            <!-- Error Messages -->
            <p class="re-keys">
               <!-- Blank -->
      			<label class="which-key" for="error_blank">No response entered:</label>
      			<input name="error_blank" id="error_blank" size="80" value="<?php echo $optionarray_def['error_blank']; ?>" />
      			<br />
      			<!-- Incorrect -->
      			<label class="which-key" for="error_incorrect">Incorrect response entered:</label>
      			<input name="error_incorrect" id="error_incorrect" size="80" value="<?php echo $optionarray_def['error_incorrect']; ?>" />
      		</p>
         </td>
      </th>
   </tr>
	 <tr valign="top">
			<th scope="row">General Settings</th>
			<td>
		    <!-- Whether or not to be XHTML 1.0 Strict compliant -->
				<input type="checkbox" name="re_xhtml" id="re_xhtml" value="1" <?php if($optionarray_def['re_xhtml'] == true){echo 'checked="checked"';} ?> /> <label for="re_xhtml">Be XHTML 1.0 Strict compliant. <strong>Note</strong>: Bad for users who don't have Javascript enabled in their browser (Majority do).</label>
				<br />
			</td>
		</tr>
	</table>
	<div class="submit">
		<input type="submit" name="submit" value="<?php _e('Update Options') ?> &raquo;" />
	</div>

	</form>
   <p class="copyright">&copy; Copyright 2008&nbsp;&nbsp;<a href="http://recaptcha.net">reCAPTCHA</a></p>
</div> <!-- [wrap] -->
<!-- ############################## END: ADMIN OPTIONS ##################### -->

<?php
}

/* =============================================================================
   Apply the admin menu
============================================================================= */

add_action('admin_menu', 'recaptcha_wp_add_options_to_admin');

// If no reCAPTCHA API keys have been entered
if ( !($recaptcha_opt ['pubkey'] && $recaptcha_opt['privkey'] ) && !isset($_POST['submit']) ) {
   function recaptcha_warning() {
		global $wpmu;
		
		$path = plugin_basename(__FILE__);
		$top = 0;
		if ($wp_version <= 2.5)
		$top = 12.7;
		else
		$top = 7;
		echo "
		<div id='recaptcha-warning' class='updated fade-ff0000'><p><strong>reCAPTCHA is not active</strong> You must <a href='options-general.php?page=" . $path . "'>enter your reCAPTCHA API key</a> for it to work</p></div>
		<style type='text/css'>
		#adminmenu { margin-bottom: 5em; }
		#recaptcha-warning { position: absolute; top: {$top}em; }
		</style>
		";
   }
   
   if (($wpmu == 1 && is_site_admin()) || $wpmu != 1)
		add_action('admin_footer', 'recaptcha_warning');
   
   return;
}

$mailhide_enabled = ($recaptcha_opt['use_mailhide_posts'] || $recaptcha_opt['use_mailhide_comments'] || $recaptcha_opt['use_mailhide_rss'] || $recaptcha_opt['use_mailhide_rss_comments']);

// If the mcrypt PHP module isn't loaded then display an alert
if (($mailhide_enabled && !extension_loaded('mcrypt')) && !isset($_POST['submit'])) {
   function mcrypt_warning() {
		global $wpmu;
		
		$path = plugin_basename(__FILE__);
		$top = 0;
		if ($wp_version <= 2.5)
		$top = 12.7;
		else
		$top = 7;
		echo "
		<div id='recaptcha-warning' class='updated fade-ff0000'><p><strong>MailHide is not active</strong> You must have the <a href='http://us3.php.net/mcrypt'>mcrypt</a> module loaded for it to work</p></div>
		<style type='text/css'>
		#adminmenu { margin-bottom: 5em; }
		#recaptcha-warning { position: absolute; top: {$top}em; }
		</style>
		";
   }
   
   if (($wpmu == 1 && is_site_admin()) || $wpmu != 1)
		add_action('admin_footer', 'mcrypt_warning');
   
   return;
}

// If MailHide is enabled but no keys have been entered
if ($mailhide_enabled && !($recaptcha_opt['mailhide_pub'] && $recaptcha_opt['mailhide_pub']) && !isset($_POST['submit'])) {
	function mailhide_warning() {
		global $wpmu;
		
		$path = plugin_basename(__FILE__);
		$top = 0;
      
		if ($wp_version <= 2.5)
         $top = 12.7;
		else
         $top = 7;
         
		echo "
		<div id='recaptcha-warning' class='updated fade-ff0000'><p><strong>MailHide is not active</strong> You must <a href='options-general.php?page=" . $path . "'>enter your MailHide API keys</a> for it to work</p></div>
		<style type='text/css'>
		#adminmenu { margin-bottom: 5em; }
		#recaptcha-warning { position: absolute; top: {$top}em; }
		</style>
		";
	}
   
   if (($wpmu == 1 && is_site_admin()) || $wpmu != 1)
		add_action('admin_footer', 'mailhide_warning');
   
	return;
}

/* =============================================================================
   End Apply the admin menu
============================================================================= */
?>