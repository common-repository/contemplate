<?php
/*
Plugin Name: Contemplate
Plugin URI: http://wordpress.org/extend/plugins/contemplate/
Description: Completely remove duplicate content from your site. Easily manage common blocks of text/html to reuse again and again!
Version: 2.11
Author: David Gwyer
Author URI: http://www.wpgothemes.com
*/

/*  Copyright 2009 David Gwyer (email : david@wpgothemes.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// @todo
// 1. Support shortcodes inside content templates? Initial test using do_shortcode didn't work.
// 2. Add button to backup the options to another option field. Have another button to retrieve backed up options (might want an option to view the backed up options first before retrieving?
// 3. Allow user to rename shortcodes on plugin settings page.
// 4. Have dropdown menu in editor to select from all shortcodes?
// 5. Be able to export/import content templates.
// 6. Disable the delete icons from being clicke during add/delete operations. Maybe we can generate a specific function to handle this?
// 7. Update the social media icons to be icons rather than images. See other plugins.
// 8. Add support for dynamic content templates that can be customised via shortcode attributes
// 9. Add screenshots to assets folder.
// 10. When the last content template is deleted then show the no content templates found message and hide the turn on wpauotp message.

// pcct_ prefix is derived from [p]ress [c]oders [c]ontent [t]emplate

// Plugin Hooks
register_uninstall_hook( __FILE__, 'pcct_delete_plugin_options' );
add_action( 'admin_menu', 'pcct_add_options_page' );
add_action( 'admin_enqueue_scripts', 'pcct_add_scripts' );
add_filter( 'plugin_action_links', 'pcct_plugin_action_links', 10, 2 );
add_action( 'admin_init', 'pcct_init' );
add_action( 'wp_ajax_pcct_add_control', 'pcct_ajax_add_control' );
add_action( 'wp_ajax_pcct_delete_control', 'pcct_ajax_delete_control' );
add_action( 'after_setup_theme', 'pcct_register_shortcodes' );

// Register Plugin settings
function pcct_init() {
	register_setting( 'pcct_plugin_options', 'pcct_options' );
}

// Add admin menu page for Plugin
function pcct_add_options_page() {
	global $pcct_options_page;
	$pcct_options_page = add_options_page( 'Contemplate Options Page', 'Contemplate', 'manage_options', __FILE__, 'pcct_render_form' );
}

// Add scripts to Plugin admin page (only)
function pcct_add_scripts( $hook ) {
	global $pcct_options_page;

	if ( $hook != $pcct_options_page ) {
		return;
	}

	wp_enqueue_style( 'pcct-css', plugin_dir_url( __FILE__ ) . 'css/pcct-css.css' );
	wp_enqueue_script( 'pcct-ajax', plugin_dir_url( __FILE__ ) . 'js/pcct-ajax.js', array( 'jquery' ) );
	wp_localize_script( 'pcct-ajax', 'pcct_vars', array(
		'pcct_nonce' => wp_create_nonce( 'pcct-nonce' )
	) );
}

// Register Shortcodes
function pcct_register_shortcodes() {

	$options = get_option( 'pcct_options' );

	$wpautop_status = isset($options['chk_wpautop']) ? $options['chk_wpautop'] : 0;

	// Code to add the shortcodes. Need to make it a loop.
	if ( is_array( $options ) ) {
		foreach ( $options as $option => $value ) {
			// Only process 'textar_' options so we can include other options later
			if ( strpos( $option, 'textar_' ) !== false ) {
				$index = str_replace( 'textar_', '', $option );
				if ( $value != "" ) {
					$value = trim($value);
					if( $wpautop_status )
						$value = wpautop( $value ); // Wrap content in <p> tags

					$value = str_replace( '\'', '\\\'', $value ); // Important! Allows single quotes to be used without issues
					add_shortcode( 'contemplate-' . $index, create_function( '', "return '$value';" ) );
				}
			}
		}
	}
}

// Process the Ajax request to add a control
function pcct_ajax_add_control() {

	if ( ! isset( $_POST['pcct_ajax_nonce_add'] ) || ! wp_verify_nonce( $_POST['pcct_ajax_nonce_add'], 'pcct-nonce' ) ) {
		die( 'You don\'t have permission to access the Ajax on this page!' );
	}

	$options = get_option( 'pcct_options' );

	// If options array doesn't exist then just set to 1
	$next_index = is_array( $options ) ? count( $options ) + 1 : 1;

	// If no options exist don't bother checking duplicate index
	if ( is_array( $options ) ) {
		while ( array_key_exists( 'textar_' . $next_index, $options ) ) {
			$next_index ++;
		}
	}

	// Initialise new text area to an empty string and add it to the Plugin options
	$options['textar_' . $next_index] = "";
	update_option( 'pcct_options', $options );

	// Render the table row
	pcct_render_row( $next_index, '' );

	die();
}

// Process the Ajax request to delete a control
function pcct_ajax_delete_control() {

	if ( ! isset( $_POST['pcct_ajax_nonce_del'] ) || ! wp_verify_nonce( $_POST['pcct_ajax_nonce_del'], 'pcct-nonce' ) ) {
		die( 'You don\'t have permission to access the Ajax on this page!' );
	}

	// Get the index from the var passed in
	if ( isset( $_POST['contemplate_id'] ) ) {
		$contemplate_id = str_replace( 'contemplate-', '', $_POST['contemplate_id'] );
	} else {
		die( 'Error: No Contemplate ID found. Cannot proceed with deletion.' );
	}

	$options = get_option( 'pcct_options' );

	if ( array_key_exists( 'textar_' . $contemplate_id, $options ) ) {
		unset( $options['textar_' . $contemplate_id] );
		update_option( 'pcct_options', $options );
		die( $contemplate_id );
	} else {
		die( 'Error: Content template not found!' );
	}

	die();
}

// Render Plugin options page
function pcct_render_form() {
	?>
	<div class="wrap">
		<div class="icon32" id="icon-options-general"><br></div>
		<h2 id="pcct-header-tag">Contemplate Options</h2>

		<p id="pcct-added-new">
			Create a content template and add any text, or HTML, you want (including CSS and JavaScript). However, <b>PHP code is NOT supported</b> inside content templates. Once saved, the content template shortcode can be pasted into any post, page, text widget, or post comment!
		</p>

		<p>
			Top Tip! If you add a lot of content templates you can specify description attribute inside your shortcode when adding to a post to help you keep track of what shortcode does what. e.g. you could have something like
			<code>[contemplate-1 descr="Latest product offer"]</code>. This works exactly the same as
			<code>[contemplate-1]</code> execpt the label reminds you what this content template is for!
		</p>

		<div class="pcct-spinner">
			<span class="spinner"></span><input type="submit" class="button" id="add-ct" value="Add New Content Template">
		</div>

		<form id="pcct-main-form" method="post" action="options.php">
			<?php settings_fields( 'pcct_plugin_options' ); ?>
			<?php $options = get_option( 'pcct_options' ); ?>
			<table class="form-table" id="pcct-ct-table">
				<tbody>
				<?php
				if ( is_array( $options ) ) {
					foreach ( $options as $option => $value ) {
						// Only process 'textar_' options here so we can include other options later
						if ( strpos( $option, 'textar_' ) !== false ) {
							$index = str_replace( 'textar_', '', $option );
							pcct_render_row( $index, $value );
						}
					}
				}
				?>

				<tr id="last-tr" valign="top">
					<td colspan="2">
						<label><input name="pcct_options[chk_wpautop]" type="checkbox" value="1" <?php if ( isset( $options['chk_wpautop'] ) ) {
								checked( '1', $options['chk_wpautop'] );
							} ?>> Turn on wpautop for Contemplate shortcodes (wraps &lt;p&gt; tags around content).</label>
					</td>
				</tr>

				</tbody>
			</table>

			<?php
			$btn_msg_css = $msg_css = '';
			if ( ! is_array( $options ) || ( count( $options ) == 0 ) ) {
				$btn_msg_css = ' style="display:none;"';
			} else {
				$msg_css = ' style="display:none;"';
			}
			?>

			<p id="pcct-empty-ct"<?php echo $msg_css; ?>>No content templates found. Click the
				<strong>Add New Content Template</strong> button to create one now!</p>

			<p id="pcct-empty-ct-submit" class="submit"<?php echo $btn_msg_css; ?>>
				<input type="submit" class="button-primary" id="pcct-submit" value="<?php _e( 'Save Content Templates' ) ?>">
			</p>
		</form>

		<div class="pcct-social-icons">
			<p>
				<a href="https://www.facebook.com/wpgoplugins/" title="View our Facebook page" target="_blank"><img src="<?php echo plugins_url(); ?>/contemplate/images/facebook-icon.png"></a><a href="http://www.twitter.com/dgwyer" title="Join me on Twitter" target="_blank"><img src="<?php echo plugins_url(); ?>/contemplate/images/twitter-icon.png"></a>&nbsp;<a class="button" href="https://www.wpgoplugins.com/" target="_blank">Visit our new plugin site!</a>
			</p>
		</div>

	</div>
<?php
}

function pcct_render_row( $index, $value ) {
	?>

	<tr id="pcct-ct-row-<?php echo $index; ?>" valign="top">
		<th scope="row">
			<input title="click to select shortcode" readonly class="pcct-shortcode" type="text" value="[contemplate-<?php echo $index; ?>]"><span title="Delete [contemplate-<?php echo $index; ?>]" class="dashicons dashicons-dismiss pcct-del-icon" id="contemplate-<?php echo $index; ?>"></span>
		</th>
		<td>
			<?php
			//$args = array("textarea_name" => "pcct_options[textar_".$index."]", "textarea_rows" => 9, "editor_class" => "pcct-textarea");
			//wp_editor( $value, "pcct_options[textar_".$index."]", $args );
			?>
			<textarea class="pcct-textarea" name="pcct_options[<?php echo 'textar_' . $index; ?>]" rows="7" type='textarea'><?php echo $value; ?></textarea>
		</td>
	</tr>

<?php
}

// Display a Settings link on the main Plugins page
function pcct_plugin_action_links( $links, $file ) {

	if ( $file == plugin_basename( __FILE__ ) ) {
		$posk_links = '<a href="' . get_admin_url() . 'options-general.php?page=contemplate/contemplate.php">' . __( 'Settings' ) . '</a>';
		// Make the 'Settings' link appear first
		array_unshift( $links, $posk_links );
	}

	return $links;
}

// Delete options table entries ONLY when plugin deactivated AND deleted
function pcct_delete_plugin_options() {
	delete_option( 'pcct_options' );
}