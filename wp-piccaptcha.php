<?php
/*
Plugin Name: WP-picCAPTCHA
Plugin URI: http://www.blaenkdenum.com/wp-piccaptcha/
Description: Integrates picCAPTCHA anti-spam solutions with wordpress
Version: 2.9.6
Author: Jorge Peña
Email: support@piccaptcha.net
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
	if (is_file(WP_CONTENT_DIR . '/mu-plugins/wp-piccaptcha.php')) // forced activated
	   $wpmu = 1;
	else if (is_file(WP_CONTENT_DIR . '/plugins/wp-piccaptcha.php')) // optionally activated
	   $wpmu = 2;
}

if ($wpmu == 1)
   $piccaptcha_opt = get_site_option('piccaptcha'); // get the options from the database
else
   $piccaptcha_opt = get_option('piccaptcha'); // get the options from the database

// END WORDPRESS MU DETECTION
   
if ($wpmu == 1)
   require_once(WP_CONTENT_DIR . '/mu-plugins/wp-piccaptcha/piccaptchalib.php');
else
   require_once(WP_CONTENT_DIR . '/plugins/piccaptchalib.php');

// doesn't need to be secret, just shouldn't be used by any other code.
define ("RECAPTCHA_WP_HASH_SALT", "b7e0638d85f5d7f3694f68e944136d62");

/* =============================================================================
   CSS - This links the pages to the stylesheet to be properly styled
   ============================================================================= */

function pic_css() {
   global $piccaptcha_opt, $wpmu;
   
   if (!defined('WP_CONTENT_URL'))
      define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
   
   $path = WP_CONTENT_URL . '/plugins/wp-piccaptcha/piccaptcha.css';
   
   if ($wpmu == 1)
		$path = WP_CONTENT_URL . '/mu-plugins/wp-piccaptcha/piccaptcha.css';
   
   echo '<link rel="stylesheet" type="text/css" href="' . $path . '" />';
}

add_action('wp_head', 'pic_css'); // include the stylesheet in typical pages to style hidden emails
add_action('admin_head', 'pic_css'); // include stylesheet to style options page

/* =============================================================================
   End CSS
   ============================================================================= */

// If the plugin is deactivated, delete the preferences
function delete_preferences() {
   global $wpmu;

   if ($wpmu != 1)
		delete_option('piccaptcha');
}

register_deactivation_hook(__FILE__, 'delete_preferences');

/* =============================================================================
   picCAPTCHA Plugin Default Options
   ============================================================================= */

$option_defaults = array (
   'pic_bypass' => '', // whether to sometimes skip picCAPTCHAs for registered users
   'pic_bypasslevel' => '', // who doesn't have to do the picCAPTCHA (should be a valid WordPress capability slug)
   'pic_tabindex' => '5', // the default tabindex for picCAPTCHA
   'pic_comments' => '1', // whether or not to show picCAPTCHA on the comment post
   'pic_xhtml' => '0', // whether or not to be XHTML 1.0 Strict compliant
   'error_blank' => '<strong>ERROR</strong>: Please fill in the picCAPTCHA form.', // the message to display when the user enters no CAPTCHA response
   'error_incorrect' => '<strong>ERROR</strong>: That picCAPTCHA response was incorrect.', // the message to display when the user enters the incorrect CAPTCHA response
);

// install the defaults
if ($wpmu != 1)
   add_option('piccaptcha', $option_defaults, 'picCAPTCHA Default Options', 'yes');

/* =============================================================================
   End picCAPTCHA Plugin Default Options
   ============================================================================= */

/* =============================================================================
   picCAPTCHA - The picCAPTCHA comment spam protection section
   ============================================================================= */
function piccaptcha_wp_hash_comment($id)
{
	global $piccaptcha_opt;
   
	if (function_exists('wp_hash'))
		return wp_hash(RECAPTCHA_WP_HASH_COMMENT . $id);
	else
		return md5(RECAPTCHA_WP_HASH_COMMENT . $piccaptcha_opt['privkey'] . $id);
}

function piccaptcha_wp_get_html ($piccaptcha_error, $use_ssl=false) {
	global $piccaptcha_opt;
   
	return piccaptcha_get_html($piccaptcha_opt['pubkey'], $piccaptcha_error, $use_ssl, $piccaptcha_opt['pic_xhtml']);
}

/**
 *  Embeds the picCAPTCHA widget into the comment form.
 * 
 */	
function piccaptcha_comment_form() {
   global $user_ID, $piccaptcha_opt;

   // set the minimum capability needed to skip the captcha if there is one
   if ($piccaptcha_opt['pic_bypass'] && $piccaptcha_opt['pic_bypasslevel'])
      $needed_capability = $piccaptcha_opt['pic_bypasslevel'];

	// skip the picCAPTCHA display if the minimum capability is met
	if (($needed_capability && current_user_can($needed_capability)) || !$piccaptcha_opt['pic_comments'])
		return;
   
   else {
		// Did the user fail to match the CAPTCHA? If so, let them know
		if ($_GET['rerror'] == 'incorrect-captcha-sol')
		echo "<p class=\"piccaptcha-error\">" . $piccaptcha_opt['error_incorrect'] . "</p>";
   
		//modify the comment form for the picCAPTCHA widget
		$piccaptcha_js_opts = <<<OPTS
		<script type='text/javascript'>
				var RecaptchaOptions = { theme : '{$piccaptcha_opt['pic_theme']}', lang : '{$piccaptcha_opt['pic_lang']}' , tabindex : {$piccaptcha_opt['pic_tabindex']} };
		</script>
OPTS;

		if ($piccaptcha_opt['pic_xhtml']) {
		$comment_string = <<<COMMENT_FORM
				<div id="piccaptcha-submit-btn-area"><br /></div>
				<script type='text/javascript'>
				var sub = document.getElementById('submit');
				sub.parentNode.removeChild(sub);
				document.getElementById('piccaptcha-submit-btn-area').appendChild (sub);
				document.getElementById('submit').tabIndex = 6;
				if ( typeof _piccaptcha_wordpress_savedcomment != 'undefined') {
						document.getElementById('comment').value = _piccaptcha_wordpress_savedcomment;
				}
				document.getElementById('piccaptcha_table').style.direction = 'ltr';
				</script>
COMMENT_FORM;
		}
		
		else {
		$comment_string = <<<COMMENT_FORM
				<div id="piccaptcha-submit-btn-area"></div> 
				<script type='text/javascript'>
				var sub = document.getElementById('submit');
				sub.parentNode.removeChild(sub);
				document.getElementById('piccaptcha-submit-btn-area').appendChild (sub);
				document.getElementById('submit').tabIndex = 6;
				if ( typeof _piccaptcha_wordpress_savedcomment != 'undefined') {
						document.getElementById('comment').value = _piccaptcha_wordpress_savedcomment;
				}
				document.getElementById('piccaptcha_table').style.direction = 'ltr';
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
		
		echo $piccaptcha_js_opts .  piccaptcha_wp_get_html($_GET['rerror'], $use_ssl) . $comment_string;
   }
}

add_action('comment_form', 'piccaptcha_comment_form');

function piccaptcha_wp_show_captcha_for_comment() {
   global $user_ID;
   return true;
}

$piccaptcha_saved_error = '';

/**
 * Checks if the picCAPTCHA guess was correct and sets an error session variable if not
 * @param array $comment_data
 * @return array $comment_data
 */
function piccaptcha_wp_check_comment($comment_data) {
	global $user_ID, $piccaptcha_opt;
	global $piccaptcha_saved_error;
   	
   // set the minimum capability needed to skip the captcha if there is one
   if ($piccaptcha_opt['pic_bypass'] && $piccaptcha_opt['pic_bypasslevel'])
      $needed_capability = $piccaptcha_opt['pic_bypasslevel'];
        
	// skip the filtering if the minimum capability is met
	if (($needed_capability && current_user_can($needed_capability)) || !$piccaptcha_opt['pic_comments'])
		return $comment_data;
   
	if (piccaptcha_wp_show_captcha_for_comment()) {
		if ( $comment_data['comment_type'] == '' ) { // Do not check trackbacks/pingbacks
			$challenge = $_POST['piccaptcha_challenge_field'];
			$response = $_POST['piccaptcha_response_field'];

			$piccaptcha_response = piccaptcha_check_answer ($piccaptcha_opt ['privkey'], $_SERVER['REMOTE_ADDR'], $challenge, $response);
			if ($piccaptcha_response->is_valid)
				return $comment_data;
			else {
				$piccaptcha_saved_error = $piccaptcha_response->error;
				add_filter('pre_comment_approved', create_function('$a', 'return \'spam\';'));
				return $comment_data;
			}
		}
	}
	return $comment_data;
}

/*
 * If the picCAPTCHA guess was incorrect from piccaptcha_wp_check_comment, then redirect back to the comment form 
 * @param string $location
 * @param OBJECT $comment
 * @return string $location
 */
function piccaptcha_wp_relative_redirect($location, $comment) {
	global $piccaptcha_saved_error;
	if($piccaptcha_saved_error != '') { 
		//replace the '#comment-' chars on the end of $location with '#commentform'.

		$location = substr($location, 0,strrpos($location, '#')) .
			((strrpos($location, "?") === false) ? "?" : "&") .
			'rcommentid=' . $comment->comment_ID . 
			'&rerror=' . $piccaptcha_saved_error .
			'&rchash=' . piccaptcha_wp_hash_comment ($comment->comment_ID) . 
			'#commentform';
	}
	return $location;
}

/*
 * If the picCAPTCHA guess was incorrect from piccaptcha_wp_check_comment, then insert their saved comment text
 * back in the comment form. 
 * @param boolean $approved
 * @return boolean $approved
 */
function piccaptcha_wp_saved_comment() {
   if (!is_single() && !is_page())
      return;

   if ($_GET['rcommentid'] && $_GET['rchash'] == piccaptcha_wp_hash_comment ($_GET['rcommentid'])) {
      $comment = get_comment($_GET['rcommentid']);

      $com = preg_replace('/([\\/\(\)\+\;\'\"])/e','\'%\'.dechex(ord(\'$1\'))', $comment->comment_content);
      $com = preg_replace('/\\r\\n/m', '\\\n', $com);

      echo "
      <script type='text/javascript'>
      var _piccaptcha_wordpress_savedcomment =  '" . $com  ."';

      _piccaptcha_wordpress_savedcomment = unescape(_piccaptcha_wordpress_savedcomment);
      </script>
      ";

      wp_delete_comment($comment->comment_ID);
   }
}

function piccaptcha_wp_blog_domain () {
	$uri = parse_url(get_settings('siteurl'));
	return $uri['host'];
}

add_filter('wp_head', 'piccaptcha_wp_saved_comment',0);
add_filter('preprocess_comment', 'piccaptcha_wp_check_comment',0);
add_filter('comment_post_redirect', 'piccaptcha_wp_relative_redirect',0,2);

function piccaptcha_wp_add_options_to_admin() {
   global $wpmu;

   if ($wpmu == 1 && is_site_admin()) {
		add_submenu_page('wpmu-admin.php', 'picCAPTCHA', 'picCAPTCHA', 'manage_options', __FILE__, 'piccaptcha_wp_options_subpanel');
		add_options_page('picCAPTCHA', 'picCAPTCHA', 'manage_options', __FILE__, 'piccaptcha_wp_options_subpanel');
   }
   else if ($wpmu != 1) {
		add_options_page('picCAPTCHA', 'picCAPTCHA', 'manage_options', __FILE__, 'piccaptcha_wp_options_subpanel');
   }
}

function piccaptcha_wp_options_subpanel() {
   global $wpmu;
	// Default values for the options array
	$optionarray_def = array(
		'pic_bypasslevel' => '3',
		'pic_tabindex' => '5',
		'pic_comments' => '1',
		'pic_xhtml' => '0',
    'error_blank' => '<strong>ERROR</strong>: Please fill in the picCAPTCHA form.',
    'error_incorrect' => '<strong>ERROR</strong>: That picCAPTCHA response was incorrect.',
		);

	if ($wpmu != 1)
		add_option('piccaptcha', $optionarray_def, 'picCAPTCHA Options');

	/* Check form submission and update options if no error occurred */
	if (isset($_POST['submit'])) {
		$optionarray_update = array (
		'pic_bypass' => $_POST['pic_bypass'],
		'pic_bypasslevel' => $_POST['pic_bypasslevel'],
		'pic_tabindex' => $_POST['pic_tabindex'],
		'pic_comments' => $_POST['pic_comments'],
		'pic_xhtml' => $_POST['pic_xhtml'],
    'error_blank' => $_POST['error_blank'],
    'error_incorrect' => $_POST['error_incorrect'],
		);
	// save updated options
	if ($wpmu == 1)
		update_site_option('piccaptcha', $optionarray_update);
	else
		update_option('piccaptcha', $optionarray_update);
}

	/* Get options */
	if ($wpmu == 1)
		$optionarray_def = get_site_option('piccaptcha');
   else
		$optionarray_def = get_option('piccaptcha');

/* =============================================================================
   picCAPTCHA Admin Page and Functions
   ============================================================================= */
   
/*
 * Display an HTML <select> listing the capability options for disabling security 
 * for registered users. 
 * @param string $select_name slug to use in <select> id and name
 * @param string $checked_value selected value for dropdown, slug form.
 * @return NULL
 */
 
function piccaptcha_dropdown_capabilities($select_name, $checked_value="") {
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
 } // end piccaptcha_dropdown_capabilities()
   
?>

<!-- ############################## BEGIN: ADMIN OPTIONS ################### -->
<div class="wrap">
	<h2>picCAPTCHA Options</h2>
	<h3>About picCAPTCHA</h3>
	<p>picCAPTCHA is a free, accessible CAPTCHA service that helps to digitize books while blocking spam on your blog.</p>
	
	<p>picCAPTCHA asks commenters to retype two words scanned from a book to prove that they are a human. This verifies that they are not a spambot while also correcting the automatic scans of old books. So you get less spam, and the world gets accurately digitized books. Everybody wins! For details, visit the <a href="http://piccaptcha.net/">picCAPTCHA website</a>.</p>
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
			<!-- Show picCAPTCHA on the comment post -->
			<big><input type="checkbox" name="pic_comments" id="pic_comments" value="1" <?php if($optionarray_def['pic_comments'] == true){echo 'checked="checked"';} ?> /> <label for="pic_comments">Enable picCAPTCHA for comments.</label></big>
			<br />
			<!-- Don't show picCAPTCHA to admins -->
			<div class="theme-select">
			<input type="checkbox" id="pic_bypass" name="pic_bypass" <?php if($optionarray_def['pic_bypass'] == true){echo 'checked="checked"';} ?>/>
			<label name="pic_bypass" for="pic_bypass">Hide picCAPTCHA for <strong>registered</strong> users who can:</label>
			<?php piccaptcha_dropdown_capabilities('pic_bypasslevel', $optionarray_def['pic_bypasslevel']); // <select> of capabilities ?>
			</div>

			<!-- Tab Index -->
			<label for="pic_tabindex">Tab Index (<em>e.g. WP: <strong>5</strong>, WPMU: <strong>3</strong></em>):</label>
			<input name="pic_tabindex" id="pic_tabindex" size="5" value="<?php  echo $optionarray_def['pic_tabindex']; ?>" />
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
				<input type="checkbox" name="pic_xhtml" id="pic_xhtml" value="1" <?php if($optionarray_def['pic_xhtml'] == true){echo 'checked="checked"';} ?> /> <label for="pic_xhtml">Be XHTML 1.0 Strict compliant. <strong>Note</strong>: Bad for users who don't have Javascript enabled in their browser (Majority do).</label>
				<br />
			</td>
		</tr>
	</table>
	<div class="submit">
		<input type="submit" name="submit" value="<?php _e('Update Options') ?> &raquo;" />
	</div>

	</form>
   <p class="copyright">&copy; Copyright 2008&nbsp;&nbsp;<a href="http://piccaptcha.net">picCAPTCHA</a></p>
</div> <!-- [wrap] -->
<!-- ############################## END: ADMIN OPTIONS ##################### -->

<?php
}

/* =============================================================================
   Apply the admin menu
============================================================================= */

add_action('admin_menu', 'piccaptcha_wp_add_options_to_admin');

// If no picCAPTCHA API keys have been entered
if ( !($piccaptcha_opt ['pubkey'] && $piccaptcha_opt['privkey'] ) && !isset($_POST['submit']) ) {
   function piccaptcha_warning() {
		global $wpmu;
		
		$path = plugin_basename(__FILE__);
		$top = 0;
		if ($wp_version <= 2.5)
		$top = 12.7;
		else
		$top = 7;
		echo "
		<div id='piccaptcha-warning' class='updated fade-ff0000'><p><strong>picCAPTCHA is not active</strong> You must <a href='options-general.php?page=" . $path . "'>enter your picCAPTCHA API key</a> for it to work</p></div>
		<style type='text/css'>
		#adminmenu { margin-bottom: 5em; }
		#piccaptcha-warning { position: absolute; top: {$top}em; }
		</style>
		";
   }
   
   if (($wpmu == 1 && is_site_admin()) || $wpmu != 1)
		add_action('admin_footer', 'piccaptcha_warning');
   
   return;
}a

/* =============================================================================
   End Apply the admin menu
============================================================================= */
?>