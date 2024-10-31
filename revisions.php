<?php
/*
Plugin Name: Revisions
Plugin URI: http://www.codehooligans.com/2008/04/18/versioning-your-blog-content/
Description: Provide Versioning, Preview and Rollback ability on Pages and Posts. 
Author: Paul Menard
Version: 1.8.3
Author URI: http://www.codehooligans.com

Revision history
1.0 - 2008-03-16: Initial release
1.1 - 2008-03-21: Fixed slight error in INSERT SQL for the new Revision. Also fixed escape on inserted content.
1.1b- 2008-04-04: Changed the 'post_status' value on the saved revision to 'inherit' as this was causing problems with some other plugins. 
1.1c- 2008-04-17: Added notation to indication current version.
1.2 - 2008-04-18: Changes to Admin display section to make things work under WP 2.5
1.8 - 2008-05-04: changes to enhance WP 2.5 design integration. Added ability to delete revisions, set a no revision 'minor-edit' option, set a no revisions for life checkbox. Display on last 5 revisions with link to reveal more. 
1.8.3 - 2008-05-23: Minor changes to display. Added link to reload most current version of content. 

*/

class Revisions
{
	var $wp_version;
	var $plugindir_url;
	
	var $_cfg;
	var $_admin_menu_label;
	var $_options_key;

	var $_post_type_name;
	var $_meta_key_name;

	var $p_versions;
	var $p_versions_read;
	var $p_versions_cnt;
	
	var $p_version_display_cnt;
	
	var $jquery_loaded;

	function Revisions()
	{
		global $wp_version;
		
		$this->wp_version 				= $wp_version;
		$this->_admin_menu_label		= "Revisions";
		$this->_options_key				= "revisions";

		$this->_post_type_name 			= "_versioning";
		$this->_meta_key_name 			= "_versioning_number";
		$this->_post_status_name 		= "inherit";
		
		$this->plugindir_url 			= get_bloginfo('wpurl') . "/wp-content/plugins/". dirname(plugin_basename(__FILE__));

		$this->p_versions_read 			= 0;
		$this->p_version_display_cnt	= 4;
		
		add_action('admin_head', array(&$this,'admin_head'));
		add_filter('the_posts', array(&$this,'show_post_revision'));
		add_action('delete_post', array(&$this,'delete_pagepost'));		
		add_filter('the_editor_content', array(&$this,'load_content'));

		add_action('admin_menu', array(&$this,'admin_init_proc'));

		// If the user is running 2.5 or higher we can rely on the new action 'pre_post_update'. since our function 
		// will be called before the post is actually updated. Otherwise we hook into the init proc when WP is first loading. 
		if ($this->wp_version >= 2.5)
		{
			add_action('pre_post_update', array(&$this,'pre_post_update_proc'));		
			add_action('submitpost_box', array(&$this,'submitpost_box_proc'));
			add_action('submitpage_box', array(&$this,'submitpost_box_proc'));
		}
	}

	function admin_init_proc()
	{
		if (function_exists('add_meta_box')) {
			add_meta_box($this->_options_key, $this->_admin_menu_label, array(&$this,'show_version_dbx'), 'post');
			add_meta_box($this->_options_key, $this->_admin_menu_label, array(&$this,'show_version_dbx'), 'page');		
		}
		else { 
			add_action('dbx_page_advanced', array(&$this,'show_version_dbx'));
			add_action('dbx_post_advanced', array(&$this,'show_version_dbx'));
		}
		
		if ($this->wp_version < 2.5)
		{
			if ((isset($_REQUEST['post_ID'])) && (intval($_REQUEST['post_ID'] > 0))
			 && (
					(isset($_REQUEST['save'])) && (strlen($_REQUEST['save']))
			  	 || (isset($_REQUEST['publish'])) && (strlen($_REQUEST['publish']))) )
			{
				$this->pre_post_update_proc($_REQUEST['post_ID']);
			}
		}		
		if (function_exists('wp_enqueue_script'))
		{
			wp_enqueue_script('jquery');
			$this->jquery_loaded = true;
		}
		else
		{
			$this->jquery_loaded = false;
		}
	}

	function admin_head()
	{
		?>
		<link rel="stylesheet" href="<?php echo $this->plugindir_url ?>/revisions_style_admin.css"
		 type="text/css" media="screen" />

		<?php
			if ($this->jquery_loaded == false)
			{
				?><script type="text/javascript" src="<?php echo $this->plugindir_url ?>/jquery.js"><?php
			}
		?>
		<script type="text/javascript">
			//<![CDATA[
			// JavaScript Document
			jQuery(document).ready(function(){

				jQuery('div#revision-hidden').hide();
				jQuery("a#revision-action-anchor").html('Click here show all Revisions')

				jQuery("a#revision-action-anchor").toggle(
					function () {
						jQuery("div#revision-hidden").slideDown("slow");
						jQuery("a#revision-action-anchor").html('Click here close Revisions')
						return false;
					},
					function () {
						jQuery("div#revision-hidden").slideUp("slow");
						jQuery("a#revision-action-anchor").html('Click here show all Revisions')
						return false;
					}
				);

				<?php
				if ($this->wp_version >= 2.5)
				{
					?>
					jQuery("input#revision-minor-edit-input-sideinfo").click(
						function () {
							var checked_status = this.checked; 
			                jQuery("input#revision-minor-edit-input").each(function() 
			                { 
			                    this.checked = checked_status; 
			                });
						}
					);
					jQuery("input#revision-minor-edit-input").click(
						function () {
							var checked_status = this.checked; 
			                jQuery("input#revision-minor-edit-input-sideinfo").each(function() 
			                { 
			                    this.checked = checked_status; 
			                });
						}
					);

					jQuery("input#revision-status-input-sideinfo").click(
						function () {
							var checked_status = this.checked; 
			                jQuery("input#revision-status-input").each(function() 
			                { 
			                    this.checked = checked_status; 
			                });
						}
					);
					jQuery("input#revision-status-input").click(
						function () {
							var checked_status = this.checked; 
			                jQuery("input#revision-status-input-sideinfo").each(function() 
			                { 
			                    this.checked = checked_status; 
			                });
						}
					);
					<?php
				}
			?>
			});
			//]]>
			</script>
		<?php 
	}

	function submitpost_box_proc()
	{
		global $post;

		if (!$post->ID)
		{
			$revision_status = "off";
		}
		else
		{
			$this->load_p_versions($post->ID);

			$revision_status = get_post_meta($post->ID, "revision_status", true);
			if ($revision_status !== 'on')
				$revision_status = "off";
			else
			{
				$revision_status_info_str = "";
				$revision_status_info = get_post_meta($post->ID, "revision_status_info", true);
				if ($revision_status_info)
				{
					$revision_status_info = unserialize($revision_status_info);
					
					$author_user = get_userdata($revision_status_info['user_id']);
					$date = mysql2date(get_option('date_format'), $revision_status_info['datetime']);
					$time = mysql2date(get_option('time_format'), $revision_status_info['datetime']);
					$revision_status_info_str = sprintf(__(' Set by %s on %s at %s'), 
						wp_specialchars( $author_user->display_name ),
						$date, $time);
				}
			}
		}
		
		?>
		
		<div class="side-info">
			<h5><?php _e('Revisions') ?></h5>
			<div class="revision-minor-edit">
				<input type="checkbox" name="revision-minor-edit" 
					id="revision-minor-edit-input-sideinfo" /><?php _e('Minor Edit'); ?>
			</div>
			<div class="revision-status">
				<input type="checkbox" <?php if ($revision_status == "on") echo 'checked="checked"'; ?>
					name="revision-status-sideinfo" 
					id="revision-status-input-sideinfo" /><?php 
						if ($revision_status == "on") 
						{ ?><span class="warning"><?php _e('Revisions are DISABLED.') ?> 
							<?php //echo $revision_status_info_str; ?></span><?php }
						else
						{ _e('Revision are Enabled'); }?>
			</div>
			<a href="#revisions" id="revision-details-link">Revision Details</a>
		</div>
		<?php
	}

	function load_content($post_content)
	{
		$rollback_revision = (int) $_REQUEST['rollback_revision'];
		$post_id = (int) $_REQUEST['post'];

		// If this is a normal processing. In other words we didn't get our required argument then return.
		if (( empty($rollback_revision) ) && (empty($post_id)))
			return $post_content;

		$post_rev =  $this->get_single_revision($post_id, $rollback_revision);
		
		// If not found return
		if ( empty($post_rev) )
			return $post_content;
		
		$post_content = $post_rev[0]->post_content;

		return $post_content;
	}

	function pre_post_update_proc($post_id) {

		global $current_user;

		if (!$post_id)
			return;

		// We don't want to create a version for autosaves
		if ($_REQUEST['action'] == "autosave")
			return;

		// Load up all the current version. 
		$this->load_p_versions($post_id);
		
		$this->process_revision_deletion($post_id);

		// Note the 'revision_status' values are reverse. The 'on' state from the input checkbox trapped here means the user
		// wishes to turn 'off' revisions for this post. So in other words it's the Bizzaro world where on is off and off is on.
		if ($_REQUEST['revision-status'] == "on")
		{
			// If we don't yet have the status in postmeta for this ID. Then set it.
			$revision_status = get_post_meta($post_id, "revision_status", true);
			if (!$revision_status)
			{
				if (!update_post_meta($post_id, "revision_status", "on"))
				{
					add_post_meta($post_id, "revision_status", "on");
				}

				$revision_status_info['user_id'] 	= $current_user->ID;
				$revision_status_info['datetime'] 	= date('Y-m-d H:i:s');
			
				if (!update_post_meta($post_id, "revision_status_info", serialize($revision_status_info)))
				{
					add_post_meta($post_id, "revision_status_info", serialize($revision_status_info));
				}
			}
			return;
		}
		else
		{
			delete_post_meta($post_id, "revision_status");
			delete_post_meta($post_id, "revision_status_info");
		}

		// Has the user set this as a minor edit to ignore?
		if ($_REQUEST['revision-minor-edit'] == "on")
			return;

		$this->make_version($post_id);		
	}
	
	function process_revision_deletion($post_id)
	{
		global $wpdb;
		
		foreach($this->p_versions as $p_idx => $p_version)
		{
			$revision_delete_token = "revision-delete-" .$p_version->version_number;
			if ($_REQUEST[$revision_delete_token] == "on")
			{
				delete_post_meta($p_version->ID, "_versioning_number", $p_version->version_number);
				$wpdb->query("DELETE FROM $wpdb->posts WHERE ID=". $p_version->ID);
				unset($this->p_versions[$p_idx]);
			}
		}
	}
	
	function delete_pagepost($post_id)
	{
		global $wpdb;
		
		if (!$post_id)
			return;

		$this->load_p_versions($post_id);

		if ($this->p_versions_cnt > 0)
		{
			$list_post_ids;
			foreach($this->p_versions as $p_version)
			{
				delete_post_meta($p_version->ID, $this->_meta_key_name);
				if (strlen($list_post_ids))
					$list_post_ids .= ",";
				$list_post_ids .= $p_version->ID;
			}
			if (strlen($list_post_ids))
			{
				$version_sql = "DELETE FROM $wpdb->posts 
							WHERE post_parent=". $post_id ." AND ID IN (".$list_post_ids.");";
				//echo "version_sql=[". $version_sql."]<br />";
				$p_version = $wpdb->get_results($version_sql);				
			}
		}
	}

	function make_version($post_id)
	{
		global $wpdb;

		$previous_version = get_post($post_id);		
		$next_version_number = $this->get_next_revision_number();

		$val_str = "";
		$key_str = "";
		
		// Next, get the fields contained in the wp-posts table.
		$field_info = $wpdb->get_results("SHOW COLUMNS FROM $wpdb->posts");
		if (!$field_info)
			return;
		
		$post_fields = array();
		foreach($field_info as $field_item)
		{
			$post_fields[$field_item->Field] = "";
		}
		
		foreach($previous_version as $key => $val)
		{
			if (!array_key_exists($key, $post_fields))
				continue;
				
			if (strlen($key_str) > 0)
				$key_str .= ", ";
			$key_str .= " `".$key."` ";

			if ($key == "ID")
				$val = "0";

			if ($key == "post_parent")
				$val = $previous_version->ID;

			if ($key == "post_type")
				$val = $this->_post_type_name;				
				
			if ($key == "post_status")
				$val = $this->_post_status_name;
			
			if ($key == "post_modified")
			{
				if ($val == "0000-00-00 00:00:00")
					$val = date('Y-m-d H:i:s');
			}

			if (strlen($val_str) > 0)
				$val_str .= ", ";
			$val_str .= " '".mysql_escape_string($val)."' ";
		}
		$version_sql = "INSERT INTO $wpdb->posts (".$key_str.") VALUES(".$val_str.");";		
		//echo "version_sql=[". $version_sql. "]<br>";
		$wpdb->query($version_sql);
		$new_post_ID = (int) $wpdb->insert_id;
		add_post_meta($new_post_ID, $this->_meta_key_name, $next_version_number);
	}

	function show_admin_advanced($type='')
	{
		global $wp_version;
		
		switch($type)
		{
			case 'begin':
				if ($wp_version < "2.5")
				{
					?>
					<div class="dbx-b-ox-wrapper">
						<fieldset id="dbx-versions" class="dbx-box">
							<div class="dbx-h-andle-wrapper">
								<h3 class="dbx-handle"><?php _e($this->_admin_menu_label) ?></h3>
							</div>
							<div class="dbx-c-ontent-wrapper">
								<div class="dbx-content">
					<?php
				}
				break;
			
			case 'end':
				if ($wp_version < "2.5")
				{
					?>							
								</div>
							</div>
						</fieldset>
					</div>
					<?php
				}
				break;
				
			default:
				break;
		}
	}

	function show_version_dbx()
	{
		global $post, $current_user;
		
		
		if ( !current_user_can('edit_post') )
			return;

		if (!$post->ID)
		{
			$revision_status = "off";
			$this->p_versions_cnt = 0;			
		}
		else
		{
			$this->load_p_versions($post->ID);

			$revision_status = get_post_meta($post->ID, "revision_status", true);
			if ($revision_status !== 'on')
				$revision_status = "off";
			else
			{
				$revision_status_info_str = "";
				$revision_status_info = get_post_meta($post->ID, "revision_status_info", true);
				if ($revision_status_info)
				{
					$revision_status_info = unserialize($revision_status_info);
					
					$author_user = get_userdata($revision_status_info['user_id']);
					$date = mysql2date(get_option('date_format'), $revision_status_info['datetime']);
					$time = mysql2date(get_option('time_format'), $revision_status_info['datetime']);
					$revision_status_info_str = sprintf(__(' Set by %s on %s at %s'), 
						wp_specialchars( $author_user->display_name ),
						$date, $time);
				}
			}
		}

		$this->show_admin_advanced('begin');

		?>
		<ul id="revisions-header">
			<li>
				<div class="revision-minor-edit">
					<input type="checkbox" name="revision-minor-edit" 
					id="revision-minor-edit-input" /><label for="revision-minor-edit-input"><?php 
						_e('Minor Edit &mdash Similar to a Wiki. This 
					is a one-time use feature that will not create a new Revision for the given edit. '); ?></label>
				</div>
			</li>
			<li>
				<div class="revision-status">
					<input type="checkbox" <?php if ($revision_status == "on") echo 'checked="checked"'; ?>
						name="revision-status" id="revision-status-input" /><label for="revision-status-input"><?php 
							if ($revision_status == "on") 
							{ ?><span class="warning"><?php _e('Revisions are DISABLED for this item.') ?> 
								<?php echo $revision_status_info_str; ?></span><?php }
							else
							{ _e('Revision are Enabled - Set this checkbox to disable Revisions for this post.'); }?></label>
				</div>
			</li>
		</ul>
		<?php
		if ($this->p_versions_cnt > 0)
		{
			?>
			<hr />
			<?php
				if (isset($_REQUEST['rollback_revision']))
				{
					$showing_version = $_REQUEST['rollback_revision'];
					?><p><a href="<?php echo get_edit_post_link($post->ID) 
						?>">Load the most current version of content into editor.</a></p><?php
				}
				else
				{
					?><p><strong>Your are viewing the most current Revision of the content.</strong></p><?php
				}
			?>
			<p><?php _e('Delete a Revision by setting the checkbox below then click on the Save post button.') ?></p>
			<ul id="revision-list">
			<?php
				$displayed_cnt = 0;
				foreach($this->p_versions as $p_version) 
				{
					$mod_author 	= get_userdata($p_version->post_author);
					$mod_date 		= mysql2date(get_settings('date_format'),
											$p_version->post_modified);
					$mod_time 		= mysql2date(get_settings('time_format'),
											$p_version->post_modified);

					?>
					<li <?php if ($p_version->version_number == $showing_version)
							{ ?> class="revision-current" <?php } ?>>
						<?php if ($p_version->version_number == $showing_version)
						{ ?><div class="revision-current-label"><?php _e('Currently showing in editor');?></div>
						<div class="input-spacer"></div><?php } ?>							
						<input type="checkbox" id="revision-delete-<?php echo $p_version->version_number; ?>"
							name="revision-delete-<?php echo $p_version->version_number; ?>" />
						<?php _e('Revision:');?> <?php echo $p_version->version_number; ?> 
							&mdash; <?php _e('Modified'); ?> <?php echo $mod_date ?> at <?php echo $mod_time ?> 
							by <?php echo wp_specialchars($mod_author->display_name) ?>

						<ul>
							<li><a href="<?php echo $this->get_revision_link($post->ID, 
								$p_version->version_number); ?>"><?php _e('View Revision'); ?></a>
							</li>

							<li><a href="<?php echo $this->get_rollback_link($post->ID, 
								$p_version->version_number, $this->p_versions_cnt); 
								?>"><?php _e('Load this Revision in Editor'); ?></a></li>
						</ul>
					</li>
					<?php
						$displayed_cnt += 1;
						$showing_hidden_div = false;

						if ((($displayed_cnt) == $this->p_version_display_cnt)
						 && (count($this->p_versions) > $this->p_version_display_cnt))
						{
							?><li><a href="" onclick="return false;" 
									id="revision-action-anchor">Display all Revisions</a></li><?php
							$showing_hidden_div = true;
							?><div id="revision-hidden"><?php
						}
					?>
					<?php
				}
				?>
			</ul>
			<?php
		}
		$this->show_admin_advanced('end');
	}

	/* The basic task here is to display the revision content */
	function show_post_revision($posts) {

		if ( !current_user_can('edit_post', $post_id) )
			return $posts;

		$revision_number_view = (int) $_REQUEST['view_revision'];
		$revision_number_from = (int) $_REQUEST['diff_from'];

		// If this is a normal processing. In other words we didn't get our required argument then return.
		if ( empty($revision_number_view) )
			return $posts;
		
		// We only want the processing for a single post or a page.
		if ((!is_single()) && (!is_page()))
			return $posts;
		
		
		// Are we viewing the rev number or diffing it to the original?
		if ( empty($revision_number_from) )
		{
			// Get the posts content for the revision_number requested.
			$post_rev_view =  $this->get_single_revision($posts[0]->ID, $revision_number_view);

			// If not found return
			if ( empty($post_rev_view) )
				return $posts;

			// The from param was not found so assume just viewing
			// copy the ID and post_type of the passed post array over to the post_rev.
			$post_rev_view[0]->ID 			= $posts[0]->ID;
			$post_rev_view[0]->post_type 	= $posts[0]->post_type;

			// Then copy the entire array back on top of the passed post array. 
			$posts[0] = $post_rev_view[0];			
		}
		else
		{
			$post_rev_from =  $this->get_single_revision($posts[0]->ID, $revision_number_view);
			if ( empty($post_rev_from) )
				return $posts;
/*
			$posts[0]->post_content = $this->diff_content(apply_filters('the_content',
												$post_rev_from[0]->post_content), 
												apply_filters('the_content', $posts[0]->post_content));
*/
/*
			$posts[0]->post_content = $this->diff_content($posts[0]->post_content,
				 											$post_rev_from[0]->post_content);
*/
			$posts[0]->post_content = $this->diff_via_url($posts[0]->ID,
													$revision_number_view,
													$revision_number_from);
		}
		return $posts;
	}
	
	
	function rollback_post_revision($post_id, $revision_number) {
		$post_rev_from =  $this->get_single_revision($posts[0]->ID, $revision_number_view);

		$rev = blicki_get_revision($post_id, $revision);

		if ( empty($rev) )
			return false;

		$rev->ID = $post_id;
		unset($rev->post_revision_author);
		unset($rev->post_revision_author_IP);
		$rev = get_object_vars($rev);
		$rev = add_magic_quotes($rev);

		return wp_update_post($rev);
	}

	function load_p_versions($post_id = '')
	{
		global $wpdb;

		if (!$post_id)
			return;

		$version_sql = "SELECT $wpdb->posts.*, $wpdb->postmeta.meta_value as version_number
					FROM $wpdb->posts
					INNER JOIN $wpdb->postmeta 
					ON $wpdb->postmeta.post_id=$wpdb->posts.ID 
						AND $wpdb->postmeta.meta_key = '".$this->_meta_key_name."'
					WHERE $wpdb->posts.`post_parent`=".$post_id." 
					AND $wpdb->posts.`post_type`='".$this->_post_type_name."'
					ORDER BY $wpdb->postmeta.meta_value DESC";
		//echo "version_sql=[". $version_sql."]<br />";

		$this->p_versions = $wpdb->get_results($version_sql);
		
		$this->p_versions_read = 1;
		
		if ($this->p_versions)
			$this->p_versions_cnt = $this->p_versions[0]->version_number;
		else
			$this->p_versions_cnt = 0;
	}

	function get_next_revision_number() {

		if ($this->p_versions_read == 0)
			$this->load_p_versions($post_id);

		return intval($this->p_versions_cnt + 1);
	}

	function get_revision_link($post_id, $revision) {
		$link = get_permalink($post_id);

		$args = array('view_revision' => $revision, 'preview' => 'true');

		//return add_query_arg('view_revision', $revision, $link);
		return add_query_arg($args, $link);
	}

	function get_diff_link($post_id, $revision, $from) {
		$link = get_permalink($post_id);

		$args = array('view_revision' => $revision, 'diff_from' => $from, 'preview' => 'true');
		return add_query_arg($args, $link);
	}

	function get_rollback_link($post_id, $revision) {
		$link = $_SERVER['REQUEST_URI'];
		return add_query_arg('rollback_revision', $revision, $link);
	}

	function get_single_revision($post_id, $revision_number)
	{
		global $wpdb;

		if (!$post_id)
			return;

		$version_sql = "SELECT $wpdb->posts.*, $wpdb->postmeta.meta_value as version_number
					FROM $wpdb->posts
					INNER JOIN $wpdb->postmeta 
					ON $wpdb->postmeta.post_id=$wpdb->posts.ID 
						AND $wpdb->postmeta.meta_key = '".$this->_meta_key_name."'
					WHERE $wpdb->posts.`post_parent`=".$post_id." 
					AND $wpdb->postmeta.`meta_value`=".$revision_number." 
					AND $wpdb->posts.`post_type`='".$this->_post_type_name."'";
		//echo "version_sql=[". $version_sql."]<br />";

		$p_version = $wpdb->get_results($version_sql);
		return $p_version;
	}
	
	/*
	Might consider using the online w3 diff tool. Pass in two different urls. One the current and the second the old version
		http://www.w3.org/2007/10/htmldiff?
			doc1=http%3A%2F%2Fwww.codehooligans.com&
			doc2=http%3A%2F%2Fwww.codehooligans.com/about
	*/
	function diff_content($text1 , $text2) {
		include(dirname(__FILE__) .'/Diff.php');

		$text1 = str_replace(array("\r\n", "\r"), "\n", $text1);
		$text2 = str_replace(array("\r\n", "\r"), "\n", $text2);

		$lines1 = split("\n", $text1);
		$lines2 = split("\n", $text2);

		echo "lines1:<pre>". print_r($lines1). "</pre>";
		echo "lines2:<pre>". print_r($lines2). "</pre>";

		// create the diff object
		$diff = &new Diff($lines1, $lines2);
		//$formatter = &new TableDiffFormatter();
		$formatter = &new DiffFormatter();
		$diff = $formatter->format($diff);
		//return "<table>\n" . $diff . "</table>\n";
		return $diff; 
	}
	
	
	function diff_via_url($post_id, $revision1 = '', $revision2 = '')
	{

		//$post_rev1 	=  $this->get_single_revision($post_id, $revision1);
		//$post_rev2 	=  $this->get_single_revision($post_id, $revision2);

		$link1		= $this->get_revision_link($post_id, $revision1);
		$link2		= $this->get_revision_link($post_id, $revision2);

		echo "link1=[". $link1. "]<br />";
		echo "link2=[". $link2. "]<br />";
		
		$target = "http://www.w3.org/2007/10/htmldiff?doc1=".$link1."&doc2=".$link2;
//$target = "http://www.w3.org/2007/10/htmldiff?doc1=http%3A%2F%2Fwww.codehooligans.com&doc2=http%3A%2F%2Fwww.codehooligans.com/about";
		// create a new cURL resource
		$ch = curl_init();

		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, $target);
		curl_setopt($ch, CURLOPT_HEADER, 0);

		// grab URL and pass it to the browser
		$gettwit = curl_exec($ch);
		echo "gettwit=[". $gettwit."]<br />";

		// close cURL resource, and free up system resources
		curl_close($ch);
/*

http://www.w3.org/2007/10/htmldiff?doc1=http%3A%2F%2Fwww.codehooligans.com&doc2=http%3A%2F%2Fwww.codehooligans.com/about		

*/

	}
	
}
/* Initialise outselves lambda stylee */
//add_action('plugins_loaded', create_function('','global $wp_revisions; $wp_revisions = new Revisions;'));
$wp_revisions = new Revisions();


?>