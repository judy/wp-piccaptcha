=== Plugin Name ===
Author: Clinton Judy
Tags: comments, registration, piccaptcha, antispam, captcha, wpmu
Requires at least: 2.1
Tested up to: 2.7.1
Beta tag: 0.0.1

Integrates PicCaptcha anti-spam methods with WordPress including comment, registration, and email spam protection. WPMU Compatible.

== Description ==

= What is PicCaptcha? =

[PicCaptcha](http://recaptcha.net/ "PicCaptcha") is an anti-spam method originating from [Penn State](http://www.psu.edu/index.shtml "The Pennsylvania State University").

This plugin is [WordPress MU](http://mu.wordpress.org/) compatible.

== Installation ==

To install in regular WordPress:

1. Upload the `wp-piccaptcha` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the `Plugins` menu in WordPress

To install in WordPress MU (Optional Activation by Users):

1. Follow the instructions for regular WordPress above

To install in WordPress MU (Forced Activation/Site-Wide):

1. Upload the `wp-piccaptcha` folder to the `/wp-content/mu-plugins` directory
1. **Move** the `wp-piccaptcha.php` file out of the `wp-piccaptcha` folder so that it is in `/wp-content/mu-plugins`
1. Now you should have `/wp-content/mu-plugins/wp-piccaptcha.php` and `/wp-content/mu-plugins/wp-piccaptcha/`
1. Go to the administrator menu and then go to **Site Admin > PicCaptcha**

== Requirements ==

* Your users will need to have Javascript enabled to see and complete the PicCaptcha challenge.
* Your theme must have a `do_action('comment_form', $post->ID);` call right before the end of your form (*Right before the closing form tag*). Most themes do.

== ChangeLog ==

= Version 0.0.1 =

