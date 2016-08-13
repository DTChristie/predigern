<?php
/**
 * Plugin Name: Predigern
 * Plugin URI: http://www.christie.ch/predigern
 * Description: This plugin provides Predigern specific parts (that are not provided by DCAPI)
 * Version: 1.2.1
 * Date: 2016-08-12
 * Author: David Christie
 * Author URI: http://www.christie.ch
 * Text Domain: dcapi
 * License: Private
 */
/*  Copyright 2015  David Christie  (email : david@christie.ch)
    This software may only be used in conjunction with the websites of Predigerkirche
*/

require 'src/plugin-update-checker/plugin-update-checker.php';
$MyUpdateChecker = PucFactory::buildUpdateChecker(
    'http://www.christie.ch/predigern/predigern.json',
    __FILE__,
    'predigern'
);

if (!class_exists('Predigern')) {
	class Predigern {
		public function __construct() {
			require_once('lib/acf_predigern.php');									// include the "precompiled" ACF Predigern fields	
			add_action( 'plugins_loaded', 'predigern_plugin_override' );	

// set the locale to Swiss German
//			add_filter( 'locale', array(&$this, 'set_my_locale') );
				
			$this->do_options_menus();										// add in options menus
			add_action('init', array(&$this, 'custom_items') );
			add_action('init', array(&$this, 'remove_tags') );

// Make sure that thumbnails are activated
			add_theme_support('post-thumbnails'); 

// allow use of SVG etc.
			add_filter('upload_mimes', array(&$this, 'custom_mime_types') );

// force the use of excerpts
			add_action('load-edit.php', array(&$this, 'force_excerpt') );

// add possibility to limit the post dates
			add_action('restrict_manage_posts', array(&$this, 'posts_filter_restrict_manage_posts') );
			add_action('pre_get_posts', array(&$this, 'exclude_this_post') );

// deal with ACF based start date/time fields
			add_filter('acf/load_value/name=post-end-date', array(&$this, 'load_value_post_end_date'), 10, 3);
			add_filter('acf/validate_value/name=post-end-date', array(&$this, 'acf_validate_postenddate'), 10, 4);

// remove unwanted menu items
			add_action('admin_menu', array(&$this, 'remove_admin_menu_items') );

// override spacing on ACF edit form for media in links list
			add_action('admin_head', array(&$this, 'custom_css') );

// adjust TinyMCE
			add_filter('tiny_mce_before_init', array(&$this, 'format_TinyMCE') );

// add processing for archive status
			add_action('admin_footer-edit.php', array(&$this, 'custom_bulk_admin_footer') );
			add_action('load-edit.php', array(&$this, 'custom_bulk_action') );
			add_action('admin_notices', array(&$this, 'custom_bulk_admin_notices') );

			add_action('init', array(&$this, 'register_archive_post_status') );
			add_filter('the_title', array(&$this, 'predigern_the_title'), 10, 2 );

			add_action('admin_footer-post.php', array(&$this, 'post_screen_js') );
			add_action('admin_footer-edit.php', array(&$this, 'edit_screen_js') );
			add_action('load-post.php', array(&$this, 'load_post_screen') );
			add_filter('display_post_states', array(&$this, 'display_post_states'), 10, 2 );
			add_action('save_post', array(&$this, 'save_archive_post'), 10, 3 );

// adjust preview button paths
			add_filter('preview_post_link', array(&$this, 'set_preview_path'), 10, 2);


// special column settings
			add_filter('manage_edit-category_columns', array(&$this, 'my_categories_columns_head') );
			add_filter('manage_category_custom_column', array(&$this, 'my_categories_columns_content'), 1, 3);
			
// add selected ACF fields to the Relevanssi indexing content
			add_filter('relevanssi_content_to_index', array(&$this, 'add_extra_relevanssi_content'), 10, 2);

		}

//----------------------------------------------------------------------------- Predigern routines

//		function set_my_locale( $lang ) {
//			return 'de_CH';
//		}

		protected function do_options_menus() {
			if( function_exists('acf_add_options_page') ) {
				acf_add_options_page(array(
					'page_title' 	=> 'Prediger',
					'menu_title' 	=> 'Frontseite',
					'menu_slug' 	=> 'homepage',
					'position'		=> '4',						/* after the overview but before posts */
					'capability'	=> 'edit_others_posts',		/* must be an editor */
					'redirect' 		=> false
				));
			}
			if( function_exists('acf_add_options_sub_page') ) {
				acf_add_options_sub_page(array(
					'page_title' 	=> 'Prediger',
					'menu_title' 	=> 'Optionen',
					'menu_slug' 	=> 'config',
					'capability'	=> 'edit_others_posts',		/* must be an editor */
					'parent'		=> 'homepage',
					'redirect' 		=> false
				));
			}
		}

		// Register custom posts and taxonomies 
		function custom_items() {
			// person
			$labels = array(
				'name'                => _x( 'Personen', 'Post Type General Name', 'predigern' ),
				'singular_name'       => _x( 'Person', 'Post Type Singular Name', 'predigern' ),
				'menu_name'           => __( 'Personen', 'predigern' ),
				'all_items'           => __( 'Alle Personen', 'predigern' ),
				'view_item'           => __( 'Person anschauen', 'predigern' ),
				'add_new_item'        => __( 'Neue Person hinzufügen', 'predigern' ),
				'add_new'             => __( 'Neue Person', 'predigern' ),
				'edit_item'           => __( 'Person editieren', 'predigern' ),
				'update_item'         => __( 'Person aktualisieren', 'predigern' ),
				'search_items'        => __( 'Person suchen', 'predigern' ),
				'not_found'           => __( 'Nicht gefunden', 'predigern' ),
				'not_found_in_trash'  => __( 'Nicht gefunden im Papierkorb', 'predigern' ),
			);
			$args = array(
				'label'               => __( 'Person', 'predigern' ),
				'description'         => __( 'Eine Person, die mit der Predigerkirche involviert ist', 'predigern' ),
				'labels'              => $labels,
				'supports'            => array( 'page-attributes', 'title', 'editor', 'thumbnail' ),
				'taxonomies'          => array( 'person-category' ),
				'hierarchical'        => false,
				'public'              => true,
				'show_ui'             => true,
				'menu_icon'           => 'dashicons-admin-users',
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'menu_position'       => 30,
				'can_export'          => true,
				'has_archive'         => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'capability_type'     => 'post',
			);
			register_post_type( 'person', $args );

			// person-category
			$labels = array(
				'name'                       => _x( 'Personenkategorien', 'Taxonomy General Name', 'predigern' ),
				'singular_name'              => _x( 'Personenkategorie', 'Taxonomy Singular Name', 'predigern' ),
				'menu_name'                  => __( 'Personenkategorien', 'predigern' ),
				'all_items'                  => __( 'Alle Personenkategorien', 'predigern' ),
				'new_item_name'              => __( 'Neue Personenkategorie', 'predigern' ),
				'add_new_item'               => __( 'Neue Personenkategorie hinzufügen', 'predigern' ),
				'edit_item'                  => __( 'Personenkategorien bearbeiten', 'predigern' ),
				'update_item'                => __( 'Personenkategorien aktualisieren', 'predigern' ),
				'search_items'               => __( 'Personenkategorien suchen', 'predigern' ),
				'add_or_remove_items'        => __( 'Personenkategorien zufügen oder entfernen', 'predigern' ),
				'choose_from_most_used'      => __( 'Auswählen aus meistgebrauchten', 'predigern' ),
				'not_found'                  => __( 'Nicht gefunden', 'predigern' ),
			);
			$args = array(
				'labels'                     => $labels,
				'hierarchical'               => true,
				'public'                     => true,
				'show_ui'                    => true,
				'show_admin_column'          => true,
				'show_in_nav_menus'          => true,
				'show_tagcloud'              => false,
			);
			register_taxonomy( 'person-category', array( 'person' ), $args );
			register_taxonomy_for_object_type( 'person-category', 'person' );

			// location
			$labels = array(
				'name'                => _x( 'Standorte', 'Post Type General Name', 'predigern' ),
				'singular_name'       => _x( 'Standort', 'Post Type Singular Name', 'predigern' ),
				'menu_name'           => __( 'Standorte', 'predigern' ),
				'all_items'           => __( 'Alle Standorte', 'predigern' ),
				'view_item'           => __( 'Standort anschauen', 'predigern' ),
				'add_new_item'        => __( 'Neuen Standort hinzufügen', 'predigern' ),
				'add_new'             => __( 'Neuer Standort', 'predigern' ),
				'edit_item'           => __( 'Standort editieren', 'predigern' ),
				'update_item'         => __( 'Standort aktualisieren', 'predigern' ),
				'search_items'        => __( 'Standort suchen', 'predigern' ),
				'not_found'           => __( 'Nicht gefunden', 'predigern' ),
				'not_found_in_trash'  => __( 'Nicht gefunden im Papierkorb', 'predigern' ),
			);
			$args = array(
				'label'               => __( 'Standort', 'predigern' ),
				'description'         => __( 'Orte, die für Predigerkirche von Relevanz sind', 'predigern' ),
				'labels'              => $labels,
				'supports'            => array( 'page-attributes', 'title', 'editor' ),
				'taxonomies'          => array( 'location-category' ),
				'hierarchical'        => false,
				'public'              => true,
				'show_ui'             => true,
				'menu_icon'           => 'dashicons-admin-site',
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'menu_position'       => 31,
				'can_export'          => true,
				'has_archive'         => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'capability_type'     => 'post',
			);
			register_post_type( 'location', $args );

			// location-category
			$labels = array(
				'name'                       => _x( 'Standort-Kategorien', 'Taxonomy General Name', 'predigern' ),
				'singular_name'              => _x( 'Standort-Kategorie', 'Taxonomy Singular Name', 'predigern' ),
				'menu_name'                  => __( 'Standort-Kategorien', 'predigern' ),
				'all_items'                  => __( 'Alle Standort-Kategorien', 'predigern' ),
				'new_item_name'              => __( 'Neue Standort-Kategorie', 'predigern' ),
				'add_new_item'               => __( 'Neue Standort-Kategorien hinzufügen', 'predigern' ),
				'edit_item'                  => __( 'Standort-Kategorien bearbeiten', 'predigern' ),
				'update_item'                => __( 'Standort-Kategorien aktualisieren', 'predigern' ),
				'search_items'               => __( 'Standort-Kategorien suchen', 'predigern' ),
				'add_or_remove_items'        => __( 'Standort-Kategorien zufügen oder entfernen', 'predigern' ),
				'choose_from_most_used'      => __( 'Auswählen aus meistgebrauchten', 'predigern' ),
				'not_found'                  => __( 'Nicht gefunden', 'predigern' ),
			);
			$args = array(
				'labels'                     => $labels,
				'hierarchical'               => true,
				'public'                     => true,
				'show_ui'                    => true,
				'show_admin_column'          => true,
				'show_in_nav_menus'          => true,
				'show_tagcloud'              => false,
			);
			register_taxonomy( 'location-category', array( 'location' ), $args );
			register_taxonomy_for_object_type( 'location-category', 'location' );

			function predigern_set_custom_post_types_admin_order($wp_query) {
				// Get the post type from the query
				$postType = $wp_query->query['post_type'];

				if ( ($postType == 'person') or ($postType == 'location') ) {
					$wp_query->set('orderby', 'menu_order');
					$wp_query->set('order', 'ASC');
				}
			}
			add_filter('pre_get_posts', 'predigern_set_custom_post_types_admin_order');
		}

		function remove_tags(){
		    register_taxonomy('post_tag', array());
		}

		// allow SVG files
		function custom_mime_types( $m ){			
		    $m['svg'] = 'image/svg+xml';
		    $m['svgz'] = 'image/svg+xml';
		    return $m;
		}

		// force excerpt mode
		function force_excerpt() {
				$_REQUEST['mode'] = 'excerpt';
		}

		// add possibility to limit the post dates
		function posts_filter_restrict_manage_posts(){
		    $type = 'post';
		    if (isset($_GET['post_type'])) {
		        $type = $_GET['post_type'];
		    }
		    if ($type == 'post'){
		    	$limit_to = $_GET['LIMIT_TO_DATE'];
		    	_e('Datum ');
		    	echo ': ';
?>
<input type="text" name="LIMIT_TO_DATE" class="datepick" value="<?php echo $limit_to;?>" size="10" /> 
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo plugins_url('css/default.css',__FILE__ );?>" />
<script type="text/javascript" src="<?php echo plugins_url('js/zebra_datepicker.src.js',__FILE__ );?>"></script>
<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery('.datepick').Zebra_DatePicker({readonly_element:false});
	});
</script>
<?php
		    }
		}

		// filter the edit posts page
		function exclude_this_post( $query ) {
			global $pagenow;
			$st = get_query_var('post_status');
			if ( 'edit.php' == $pagenow 
					&& ( get_query_var('post_type') && 'post' == get_query_var('post_type') ) 
					&& ( $st === '' )   ) {
				set_query_var( 'post_status', array( 'publish', 'pending', 'draft', 'future', 'private', 'inherit' ) );			// neither 'trash', 'archive' or 'auto-draft'
				if (get_query_var('order') == '') {
					set_query_var( 'order', 'ASC' );
				}

			}
			if (isset($_GET['LIMIT_TO_DATE'])) {
				if (!empty($_GET['LIMIT_TO_DATE'])) {
					preg_match("/(\d{4})-(\d{2})-(\d{2})/", $_GET['LIMIT_TO_DATE'], $to_date);
					set_query_var('date_query', array( 'year' => $to_date[1], 'month' => $to_date[2], 'day' => $to_date[3], ));
				}
			}
			return $query;
		}

		// extend ACF field processing...
		//
		// handle updating of post-end-date field
		function load_value_post_end_date( $value, $post_id, $field )
		{
		    $post = get_post($post_id);

			preg_match("/(\d{4})\-(\d{2})\-(\d{2}) (\d{2}):(\d{2})/", $post->post_date, $out);

		    return ($value) ? $value : $out[1].$out[2].$out[3];
		}

		//	validate that post-end-date is after post-start-date if present
		function acf_validate_postenddate( $valid, $value, $field, $input ) {
			// bail out early if value is already invalid
			if( !$valid ) {	
				return $valid;
			}

			if (!empty($value)) {		// there is a value
				if ($value < $_POST['acf'][field_54aaf532c2616]) {		// is post-end-date < post-start-date?
					$valid = 'Das Enddatum darf nicht vor dem Startdatum sein';
				}
			}
			return $valid;	
		}

		// remove unwanted admin menu items
		function remove_admin_menu_items() {
			if ( current_user_can('administrator') ) {
				$remove_menu_items = array(__('Comments')); 
			} else {
				$remove_menu_items = array(__('Comments'),__('Tools')); 
			}
			global $menu;
			end ($menu);
			while (prev($menu)){
				$item = explode(' ',$menu[key($menu)][0]);
				if(in_array($item[0] != NULL?$item[0]:"" , $remove_menu_items)){
				unset($menu[key($menu)]);}
				}
			}

		// override spacing on ACF edit form for media in links list
		function custom_css() {
echo '<style>
.column-new-date { width: 15%; }
.acf-file-uploader .file-info {
	padding: 10px;
	margin-left: 130px;		/* was 69px */
}
</style>';
		}

		/*
		 *		Interfere with TinyMCE (WYSIWYG editor)
		 */
		function format_TinyMCE( $in ) {
			$in['remove_linebreaks'] = false;
			$in['gecko_spellcheck'] = false;
			$in['keep_styles'] = false;			// was true;
			$in['accessibility_focus'] = true;
			$in['tabfocus_elements'] = 'major-publishing-actions';
			$in['media_strict'] = false;
			$in['paste_remove_styles'] = false;
			$in['paste_remove_spans'] = false;
			$in['paste_strip_class_attributes'] = 'none';
			$in['paste_text_use_dialog'] = true;
			$in['wpeditimage_disable_captions'] = true;
			$in['plugins'] = 'tabfocus,paste,media,fullscreen,wordpress,wpeditimage,wpgallery,wplink,wpdialogs'; // DC 2015-08-29: removed ,wpfullscreen
			$in['content_css'] = plugin_dir_url(__FILE__) . "/editor-style.css";
			$in['wpautop'] = false; 	// was true;
			$in['apply_source_formatting'] = false;
		    $in['block_formats'] = "Paragraph=p; Heading 3=h3; Heading 4=h4";
			$in['toolbar1'] = 'bold,italic,strikethrough,bullist,numlist,wp_fullscreen ';
										// was 'bold,italic,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,wp_fullscreen,wp_adv ';
			$in['toolbar2'] = ''; 		// was 'formatselect,underline,alignjustify,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help ';
			$in['toolbar3'] = '';
			$in['toolbar4'] = '';
			return $in;
		}

		// Handle the custom Bulk Action
		// Step 1: add the custom Bulk Action to the select menus
		function custom_bulk_admin_footer() {
			global $post_type;			
			if($post_type == 'post') {
?>
<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('<option>').val('archive').text('<?php _e('Archivieren')?>').appendTo("select[name='action']");
		jQuery('<option>').val('archive').text('<?php _e('Archivieren')?>').appendTo("select[name='action2']");
	});
</script>
<?php
	    	}
		}

		// Step 2: handle the custom Bulk Action
		function custom_bulk_action() {
			global $typenow;
			$post_type = $typenow;
			
			if($post_type == 'post') {
				
				// get the action
				$wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
				$action = $wp_list_table->current_action();
				
				$allowed_actions = array("archive");
				if(!in_array($action, $allowed_actions)) return;
				
				// security check
				check_admin_referer('bulk-posts');
				
				// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
				if(isset($_REQUEST['post'])) {
					$post_ids = array_map('intval', $_REQUEST['post']);
				}
				
				if(empty($post_ids)) return;
				
				// this is based on wp-admin/edit.php
				$sendback = remove_query_arg( array('archive', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
				if ( ! $sendback )
					$sendback = admin_url( "edit.php?post_type=$post_type" );
				
				$pagenum = $wp_list_table->get_pagenum();
				$sendback = add_query_arg( 'paged', $pagenum, $sendback );
				
				switch($action) {
					case 'archive':
						$archived = 0;
						foreach( $post_ids as $post_id ) {						
							if ( !$this->perform_archive($post_id) )
								wp_die( __('Error srchiving post.') );
			
							$archived++;
						}					
						$sendback = add_query_arg( array('archived' => $archived, 'ids' => join(',', $post_ids) ), $sendback );
					break;
					
					default: return;
				}
				
				$sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
				
				wp_redirect($sendback);
				exit();
			}
		}
			
		// Step 3: display an admin notice on the Posts page after archiving
		function custom_bulk_admin_notices() {
			global $post_type, $pagenow;
			
			if($pagenow == 'edit.php' && $post_type == 'post' && isset($_REQUEST['archived']) && (int) $_REQUEST['archived']) {
				$message = sprintf( _n( 'Beiträge archiviert.', '%s Beiträge archiviert.', $_REQUEST['archived'] ), number_format_i18n( $_REQUEST['archived'] ) );
				echo "<div class=\"updated\"><p>{$message}</p></div>";
			}
		}
		
		protected function perform_archive($post_id) {
			// Update post
			$my_post = [];
			$my_post['ID'] = $post_id;
			$my_post['post_status'] = 'archive';

			// Update the post into the database
			wp_update_post( $my_post );
			return true;
		}

/*
 * Add in handling for Archived status, based with thanks on plugin Archived Post Status, by Frankie Jarrett (http://frankiejarrett.com)
 */

		// Register a custom post status for Archived
		function register_archive_post_status() {
			$args = array(
				'label'                     => __( 'Archiviert', 'archived-post-status' ),
				'public'                    => false,
				'private'                   => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Archiviert <span class="count">(%s)</span>', 'Archiviert <span class="count">(%s)</span>', 'archived-post-status' ),
			);

			register_post_status( 'archive', $args );
		}

		// Filter archived post titles on the frontend
		function predigern_the_title( $title, $post_id = null ) {
			$post = get_post( $post_id );

			if ( ! is_admin() && isset( $post->post_status ) && 'archive' === $post->post_status ) {
				$title = sprintf( '%s: %s', __( 'Archiviert', 'archived-post-status' ), $title );
			}
			return $title;
		}

		// Modify the DOM on post screens
		function post_screen_js() {
			global $post;
			if ( $this->is_excluded_post_type( $post->post_type ) ) {
				return;
			}
			if ( 'draft' !== $post->post_status && 'pending' !== $post->post_status ) {
?>
<script>
	jQuery( document ).ready( function( $ ) {
		$( '#post_status' ).append( '<option value="archive"><?php esc_html_e( 'Archiviert', 'archived-post-status' ) ?></option>' );
	});
</script>
<?php
			}
		}

		// Modify the DOM on edit screens
		function edit_screen_js() {
			global $typenow;

			if ( $this->is_excluded_post_type( $typenow ) ) {
				return;
			}
?>
<script>
jQuery( document ).ready( function( $ ) {
	$rows = $( '#the-list tr.status-archive' );
	$.each( $rows, function() {
		disallowEditing( $( this ) );
	});
	$( 'select[name="_status"]' ).append( '<option value="archive"><?php esc_html_e( 'Archiviert', 'archived-post-status' ) ?></option>' );

	$( '.editinline' ).on( 'click', function() {
		var $row        = $( this ).closest( 'tr' ),
		    $option     = $( '.inline-edit-row' ).find( 'select[name="_status"] option[value="archive"]' ),
		    is_archived = $row.hasClass( 'status-archive' );
		$option.prop( 'selected', is_archived );
	});

	$( '.inline-edit-row' ).on( 'remove', function() {
		var id   = $( this ).prop( 'id' ).replace( 'edit-', '' ),
		    $row = $( '#post-' + id );
		if ( $row.hasClass( 'status-archive' ) ) {
			disallowEditing( $row );
		}
	});
	function disallowEditing( $row ) {
		var title = $row.find( '.column-title a.row-title' ).text();
		$row.find( '.column-title a.row-title' ).remove();
		$row.find( '.column-title strong' ).prepend( title );
		$row.find( '.row-actions .edit' ).remove();
	}
});
</script>
<?php
		}

		// Prevent archived content from being edited
		function load_post_screen() {
			$action  = isset( $_GET['action'] ) ? $_GET['action'] : null;
			$message = isset( $_GET['message'] ) ? absint( $_GET['message'] ) : null;
			$post_id = isset( $_GET['post'] ) ? $_GET['post'] : null;
			$post    = get_post( $post_id );

			if ( is_null( $post ) || $this->is_excluded_post_type( $post->post_type ) || 'archive' !== $post->post_status ) {
				return;
			}

			// Redirect to list table after saving as Archived
			if ( 'edit' === $action && 1 === $message ) {
				wp_safe_redirect(
					add_query_arg(
						array( 'post_type' => $post->post_type ),
						admin_url( 'edit.php' )
					),
					302
				);
				exit;
			}

			wp_die(
				__( "You can't edit this item because it has been Archived. Please change the post status and try again.", 'archived-post-status' ),
				translate( 'WordPress &rsaquo; Error' )
			);
		}

		// Display custom post state text next to post titles that are Archived
		function display_post_states( $post_states, $post ) {
			if (
				$this->is_excluded_post_type( $post->post_type ) || 'archive' !== $post->post_status || 'archive' === get_query_var( 'post_status' ) ) {
				return $post_states;
			}
			return array_merge( $post_states, array( 'archive' => __( 'Archiviert', 'archived-post-status' ) ) );
		}

		// Check if a post type should NOT be using the Archived status
		protected function is_excluded_post_type( $post_type ) {
			// Prevent the Archived status from being used on these post types
			$excluded = apply_filters( 'excluded_post_types', array( 'attachment' ) );
			if ( in_array( $post_type, $excluded ) ) {
				return true;
			}
			return false;
		}

		// Close comments and pings when content is archived
		function save_archive_post( $post_id, $post, $update ) {
			if ( $this->is_excluded_post_type( $post->post_type ) || wp_is_post_revision( $post ) ) {
				return;
			}
			if ( 'archive' === $post->post_status ) {
				// Unhook to prevent infinite loop
				remove_action( 'save_post', array(&$this, 'save_archive_post') );		//		remove_action( 'save_post', __FUNCTION__ );
				$args = array(
					'ID'             => $post->ID,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				);
				wp_update_post( $args );
				// Add hook back again
				add_action( 'save_post', array(&$this, 'save_archive_post'), 10, 3 );		//		add_action( 'save_post', __FUNCTION__, 10, 3 );
			}
		}

		// adjust preview button paths
		function set_preview_path($link, $post) {
			$surl = get_site_url();
			$pl = get_permalink($post->ID);
			$temp = explode($surl, $pl);
			$fs = trim($temp[1], '/');			// full slug

			if ($post->post_type == 'post') {
				return "$surl/beitrag/$post->ID";
			} else {
				return "$surl/$fs";
			}
		}

		// extend the manage categories screen
		function my_categories_columns_head($defaults) {
				$defaults['cat-fields'] = 'Prediger Felder';
				return $defaults;
		}

		function my_categories_columns_content($out, $column, $cat_id) {
			switch ( $column ) {

			case 'cat-fields' :
				$cat_post = get_field('post', 'category_' . $cat_id);
				$cat_subtitle = get_field('use-as-subtitle', 'category_' . $cat_id);
				$cat_picture = get_field('category-post-image', 'category_' . $cat_id);
				if (!empty($cat_subtitle)) {
					$out .= '<em>Kategorie als Untertitel benützen</em><br />';
				}
				if (!empty($cat_picture)) {
					$out .= '<em>mit Kategorie-Beitragsbild</em><br />';
				}
				if ($cat_post->ID) {
					$out .= '<em>Link zu "' . $cat_post->post_title . '"</em>';
				}
				return $out;
				break;
			}
		}

// add selected ACF fields to the Relevanssi indexing content
		function add_extra_relevanssi_content($content, $post) {

		    $contributors = get_field('contributors', $post->ID);
		    if ($contributors) {
			    $c = '';
				$sep = '';		
				foreach ($contributors as $contributor) {
					if ($contributor['person-is-internal'] == 1) {
						$c .= $sep . $contributor['person']->post_title . ' ' . $contributor['contributors-role'];
					} else {
						$c .=  $sep . $contributor['external-person'] . ' ' . $contributor['contributors-role'];
					}
					$sep = " | ";
				}
			    $content .= $c;
			}

		    $content .= get_field('short-title', $post->ID);

		    return $content;
		}

//---------------------------------------------------------------------- other routines

		protected function convert_dateTime($dateTimeType) {
			$map = array(
				'keine' => 'none',
				'Datum und Zeit' => 'dateTime',
				'nur Datum' => 'date',
				'Zeitraum' => 'dateRange',
				'Zeitraum mit Zeiten' => 'dateTimeRange',
				'wöchentlich wiederholend' => 'weeklyRepeat',
				);
			return $map[$dateTimeType];
		}
	}
}
global $Predigern;
$Predigern = new Predigern();


if (class_exists('DCAPI')) {

	//
	// register DCAPI blob processing for Predigern
	//
	global $DCAPI;

	function handle_internal_link(&$element, $post) {
		global $DCAPI;
		$element['ID'] = $post->ID;			    
		$element['type'] = $post->post_type;			    
		$element['route'] = $DCAPI->generate_route($post->ID);			    
		$element['slug'] = $post->post_name;
		$element['title'] = $post->post_title;
	}
	
	function predigern_process_excerpt($blob, $post, $transformed_value, &$out) {
		$excerpt = htmlspecialchars_decode($transformed_value);							// just in case!	
		$content = $out['content'];														// supply content via mapping if wanted

		if ($excerpt) {
			$excerpt = trim($excerpt);
		} else {
			$x = preg_replace("/<(\\/)?\w+( ?\\/)?>/", " ", $content);
	        $excerpt = wp_trim_words($x, 20);
		}
		if ($excerpt) {						// remove trailing newline (if any) and convert embedded newlines into <br />
			return preg_replace("/(\r)?\n/", "<br />", preg_replace("/^(.*)(\r)?\n$/", "$1", $excerpt));
		}
	}
	$DCAPI->register_transformation('process excerpt', 'predigern_process_excerpt');

	function predigern_process_permalink($blob, $post, $transformed_value, &$out) {
		if ($post->post_type == 'post') {
			return "beitrag/$post->ID";
		} else {
			$surl = get_site_url();
			$pl = get_permalink($post->ID);
			$temp = explode($surl, $pl);
			return trim($temp[1], '/');			// full slug
		}
	}
	$DCAPI->register_transformation('process permalink', 'predigern_process_permalink');

	function predigern_process_links($blob, $post, $transformed_value, &$out) {
		global $DCAPI;
		// process links
		$links = [];

		if (!empty($transformed_value)) {
			foreach ($transformed_value as $link) {
				$l = [];
				switch ($link['type']) {
				    case "Seite/Beitrag":
					    if ($link['post']) handle_internal_link($l, $link['post']);
				        break;
				    case "Medienbeitrag":
					    if ($link['media-item']) {
					    	$l['ID'] = $link['media-item']['ID']; 
					    	$l['type'] = 'attachment';			    
					    	$l['route'] = $DCAPI->generate_route($link['media-item']['ID']);			    
					    	$l['url'] = $DCAPI->append_timestamp($link['media-item']['url']);
					    	$l['mime_type'] = $link['media-item']['mime_type'];
					    	$l['title'] = (!empty($link['media-item']['caption'])) ? $link['media-item']['caption'] : $link['media-item']['description'];
					    	if (empty($l['title'])) {
					    		$l['title'] = $link['media-item']['title'];		// use the media title
					   		}
					    }
				        break;
				    case "Standort":
					    if ($link['location']) {
							$ID = $link['location']->ID;	
							handle_internal_link($l, $link['location']);
					    	$l['content'] = $link['location']->post_content;	
							$l['coordinate'] = get_field('coordinate', $ID);
					    }
				        break;
				    case "Kontaktperson":
					    if ($link['contact-person']) {
							$ID = $link['contact-person']->ID;
							handle_internal_link($l, $link['contact-person']);
							$l['role'] = get_field('role', $ID);
							$l['telephoneMobile'] = get_field('telephone-mobile', $ID);
							$l['telephonePrivate'] = get_field('telephone-private', $ID);
							$l['telephoneWork'] = get_field('telephone-work', $ID);
							$l['emailAddress'] = get_field('email-address', $ID);
						}
				        break;
				    case "Link":
				    	$l['ID'] = 'link';			// signal that this one is a link
				    	$l['type'] = 'link';			    
				    	$l['url'] = $link['link-url'];			    
				    	$l['title'] = preg_replace("/^https?:\/\/(.*)(\/?)$/Ui", '$1', $link['link-url']);			// strip off the http(s):// part and trailing slash if any
				        break;
				}		
				if (!empty($link['alt'])) { $l['title'] = $link['alt']; }
				if (isset($l['ID']) and ($l['ID'] != '') ) {
					if ($l['ID'] == 'link') unset($l['ID']);
					$links[] = $l;									// only links and valid IDs are output
				}	
			}
		}
		if (!empty($links)) {
			return $links; 
		}
	}
	$DCAPI->register_transformation('process links', 'predigern_process_links');

	function predigern_process_redirections($blob, $post, $transformed_value, &$out) {
		// list redirections (if any)
		$c = [];
		$reds = $transformed_value;

		if (!empty($reds)) {
			$surl = get_site_url();
			foreach ($reds as $red) {
				$p = $red['post'];
				$pl = get_permalink($p->ID);
				$temp = explode($surl, $pl);
				$fs = trim($temp[1], '/');			// full slug
				$x = [];
				handle_internal_link($x, $p);
				$x['path'] = $red['path'];
				$x['fullSlug'] = $fs;
				$c[] = $x;
			}
			return $c;
		}
	}
	$DCAPI->register_transformation('process redirections', 'predigern_process_redirections');

	function predigern_process_menu($blob, $post, $transformed_value, &$out) {
		$menu = $transformed_value;
		if (!is_array($menu)) return;

		$output = [];
		$div_found = false;
		foreach ($menu as $item) {
			$out_item = [];
			if ($item['divider']) {
				$div_found = true;			// tag the following item...
			} else {
				$surl = get_site_url();
				$p = $item['page'];
				$pl = get_permalink($p->ID);
				$temp = explode($surl, $pl);
				$fs = trim($temp[1], '/');			// full slug

				$out_item['marginBefore'] = $div_found;
				$div_found = false;
				handle_internal_link($out_item, $p);
				$out_item['fullSlug'] = $fs;
				if (!empty($item['alt'])) $out_item['title'] = $item['alt'];
				$output[] = $out_item;
			}
		}
		return $output;
	}
	$DCAPI->register_transformation('process menu', 'predigern_process_menu');

	function predigern_process_categories($blob, $post, $transformed_value, &$out) {		
		// process categories
		$cats = get_the_terms( $post->ID, $transformed_value );
		if (!empty($cats)) {
			$cat_out = [];
			foreach ($cats as $cat) {
				$c = array(
					'ID' => $cat->term_id,
					'type' => $transformed_value,
					'title' => $cat->name,
					'slug' => $cat->slug,
					);
				$cat_out[] = $c;
			}
			return $cat_out;
		}
	}
	$DCAPI->register_transformation('handle categories', 'predigern_process_categories');

	function predigern_process_dateTime($blob, $post, $transformed_value, &$out) {		
		$dateTimeType = $out['dateTimeType'];			// translated by mapping
		if (empty($dateTimeType)) return;
		date_default_timezone_set(get_option('timezone_string'));				// use the same timezone as the website

		$startDate = $out['param/SD'];		// format yyyy-mm-dd
		$endDate = $out['param/ED'];		// format yyyy-mm-dd
		$weeklyRepeat = $out['param/WR'];
		$everyXWeeks = $out['param/XW'];
		$exceptions = preg_replace('/\s+/', '', $out['param/EX']);				// strip out white space 

		if ($dateTimeType == 'weeklyRepeat') {
			if ($everyXWeeks < 0) $everyXWeeks = 1;
			$out['weeklyRepeat'] = $weeklyRepeat;
			$out['everyXWeeks'] = $everyXWeeks;
			if (!$exceptions) {
				$EX = [];				// no exceptions
				$out['exceptions'] = null;											// cleaned up formatting
			} else {
				$EX = explode(',', $exceptions);									// list of exception dates
				$exc = '';
				$sep = '';
				foreach ($EX as $key => $value) {
					$d = new DateTime($value);
					$EX[$key] = $d->format('Y-m-d');								// allow supported date formats
					$exc = $exc . $sep . $EX[$key];
					$sep = ", ";
				}
				$out['exceptions'] = $exc;											// cleaned up formatting
			}

			$sw = get_option( 'start_of_week' );								// week starts on (0=Sun, ... 6=Sat) - from Wordpress options

			$WB = new DateTime($startDate);		
			$WB->sub(new DateInterval('P'. ( ($WB->format('w')-$sw) % 7 ) .'D'));	// date at beginning of week when the repeat period starts

			$nextEvents = [];
			$SECONDS_PER_DAY = 86400;
			$today = date('Y-m-d', time());										// our dates are in ISO format
			$lower_limit = ($startDate < $today) ? $today : $startDate;

			$count = 0;															// limit number of entries
			$DT = new DateTime($lower_limit);
			$dt = $DT->format('Y-m-d'); 							// as string
			while ( ($dt <= $endDate) and ($dt >= $lower_limit) ) {
				if ($count >= 20) { $out['moreEvents'] = true; break; }						// maximum number of repeats is 20
				
				$dw = $DT->format('w');											// current day of week 0=Sun, ... 6=Sat
				if ( ($everyXWeeks != 0) and (in_array($dw, $weeklyRepeat)) ) {	// 0=Sun, .. 6=Sat
					$DB = new DateTime($dt);
					$DB->sub(new DateInterval('P'. ( ($DB->format('w')-$sw) % 7 ) .'D'));		// date at beginning of week of current date
					$intd = $DB->diff($WB)->format('%a');						// how many days ahead of start week of period
					$modw = ($intd / 7) % $everyXWeeks;							// how many weeks modulo "every x weeks" setting
					if ($modw == 0) {
						if (!in_array($dt, $EX)) { $nextEvents[] = $dt; $count++; }			// only output the repeat then, and if not in the exceptions list
					} else {
						if (in_array($dt, $EX)) { $nextEvents[] = $dt; $count++; }			// output non matching repeat if in the exceptions list
					}
				} else {
					if (in_array($dt, $EX)) { $nextEvents[] = $dt; $count++; }				// output non matching repeat if in the exceptions list
				}
				$DT->add(new DateInterval('P1D'));
				$dt = $DT->format('Y-m-d'); 	// as string
			}
			return $nextEvents;
		}
	}
	$DCAPI->register_transformation('process date/time', 'predigern_process_dateTime');

	function predigern_format_contributors($blob, $post, $transformed_value, &$out) {		
		if (!is_array($transformed_value)) return null; 				// just in case!

		$c = [];
		foreach ($transformed_value as $contributor) {
			$p = $contributor['person'];
			if ($contributor['person-is-internal'] == 1) {
				$x = [];
				handle_internal_link($x, $p);
				$x['role'] = $contributor['contributors-role'];
				$x['name'] = $p->post_title;
				unset($x['title']);
				$c[] = $x;
			} else {
				$c[] = array(
					'name' => $contributor['external-person'],
					'role' => $contributor['contributors-role'],
					);
			}
		}
		return $c;
	}
	$DCAPI->register_transformation('process contributors', 'predigern_format_contributors');

	function predigern_format_location($blob, $post, $transformed_value, &$out) {
		$loc = $transformed_value;
		if (!$loc) return;
		$ID = $loc->ID;
		if ($ID) {
			$x = [];
			handle_internal_link($x, $loc);
			$x['content'] = $loc->post_content;
			$x['terms'] = wp_get_post_terms($loc->ID, 'location-category', array('fields' => 'ids'));
			$x['coordinate'] = get_field('coordinate', $ID);
			return $x;
		}
	}
	$DCAPI->register_transformation('process location', 'predigern_format_location');

	function predigern_get_subtitle($blob, $post, $transformed_value, &$out) {
	// build up the subtitle string for a given post's set of categories
		$cats = get_the_category($post->ID);
		if (empty($cats)) {
			return null;
		}
		$outp = '';
		$sep = '';
		foreach ($cats as $cat) {
			if (get_field('use-as-subtitle', 'category_' . $cat->term_id) == 1) {
				$outp .= $sep . $cat->name;
				$sep = " | ";
			}
		}
		return $outp;
	}
	$DCAPI->register_transformation('build subtitle', 'predigern_get_subtitle');

	function predigern_feed_filter($out, $order, $orderby, $item) {		// return sort key for the items, or null if item should be dropped
		$limitLocation = $out['param/limitLocation'];
		if ( ($limitLocation) and (is_array($item['location']['terms'])) ) {
			if (!in_array($limitLocation, $item['location']['terms']) ) return null;		// do the location limitation check
		}
		$ID = $item['ID'];
		if ($orderby == 'date') {
			date_default_timezone_set (get_option('timezone_string'));
			$today = date("Y-m-d");					// format of post_date
			$item_startdate = date("Y-m-d", $item['startDateTime']);
			$item_startdatetime = date("Y-m-d_H:i", $item['startDateTime']);
			if ($item['endDateTime']) {
				$item_enddate = date("Y-m-d", intval($item['endDateTime']));			// if there is an end date take it into account and include for ascending order
			} else {
				$item_enddate = $item_startdate;			// 
			}
			if ( ($order != 'DESC') and (($item_startdate >= $today) or ($item_enddate >= $today)) ) {		// also cover "(as retrieved)"
				return $item_startdatetime . '_' . $ID;
			} elseif (($order == 'DESC') and ($item_startdate <= $today)) {
				return $item_startdatetime . '_' . $ID;
			}
		} elseif ($orderby == 'title') {
			return $item['title'];

		} elseif ($orderby == 'menu_order') {
			$menu_order = $item['menu_order'];
			$t = 1000000000 + $menu_order;
			return substr($t, 1) . '_' . $ID;

		} elseif ($orderby == 'relevance') {
			$relevance = $item['relevance'];
			$t = 1000000000 + $relevance;
			return substr($t, 1) . '_' . $ID;
		}
		return null;
	}
	$DCAPI->register_feed_filter('Prediger feed filter', 'predigern_feed_filter');

	function predigern_process_homepage_feed($blob, $post, $transformed_value, &$out) {			// returns list of posts and list of search terms in $out['param/terms']
		$postList = [];
		$termList = [];

		if ($transformed_value) foreach ($transformed_value as $hpf) {
			if ($hpf['selection-criteria'] == 'Einzelbeitrag') {
				$ip = $hpf['individual-post'];
				if (get_post_status( $ip->ID ) !== false) $postList[] = $ip;

			} else {
				date_default_timezone_set (get_option('timezone_string'));
				$year = date("Y");		
				$month = date("m");		
				$day = date("d");		
				$order = ($hpf['selection-criteria'] == 'nächster geplanter Beitrag') ? 'ASC' : 'DESC';
				$limit = ($hpf['selection-criteria'] == 'nächster geplanter Beitrag') ? 'after' : 'before';

				$args = [];						// build WP_Query args for this feed
				$args['posts_per_page'] = -1;							// unlimited
				$args['order'] = $order;
				$args['orderby'] = 'date';
				$args['post_type'] = ('post');
				$args['post_status'] = array('publish', 'future', 'inherit');		// always 
				$args['tax_query'] = array(
						array(
							'taxonomy' => 'category',					// NB: only normal 'category' posts can be shown on the front page
							'field'    => 'id',
							'terms'    => $hpf['categories'],
							'operator' => 'IN',
							'include_children' => true,
						),
					);
				$args['date_query'] = array(
						array( $limit => array('year'  => $year, 'month' => $month, 'day'   => $day, ),
						'inclusive' => true,
						),
					);
				$query = new WP_Query();
				$posts = $query->query($args);
				$termList = array_merge($termList, $hpf['categories']);

				$limitLocation = $hpf['limit-location'];
				foreach ($posts as $post) {
					$loc = get_field('post-location', $post->ID);
					$terms = wp_get_post_terms($loc->ID, 'location-category', array('fields' => 'ids'));
					if ( ($limitLocation) and (is_array($terms)) ) {
						if (!in_array($limitLocation, $terms) ) continue;		// keep looking
					}
					$postList[] = $post;			// otherwise take first item found
					break;
				}	
			}
		}
		$out['param/terms'] = $termList;
		return $postList;
	}
	$DCAPI->register_transformation('process homepage feed', 'predigern_process_homepage_feed');

	function predigern_list_contributors($blob, $post, $transformed_value, &$out) {
		$o = '';
		$i = $transformed_value;
		if ( ($i) and (is_array($i)) ) {
			$sep = '';		
			foreach ($i as $contributor) {
				$cr = $contributor['contributors-role'];
				if ( strpos($cr, 'Liturg') === false ) {				// skip Liturg* entries (for Musik mittendrin)
					if ($contributor['person-is-internal'] == 1) {
						$p = $contributor['person'];
						$o .= $sep . $p->post_title . ( ($cr) ? ', ' . $cr : '');
					} else {
						$o .= $sep . $contributor['external-person'] . ( ($cr) ? ', ' . $cr : '');
					}
					$sep = " | ";
				}
			}
		}
		return $o;
	}
	$DCAPI->register_transformation('contributors list', 'predigern_list_contributors');

	function handle_post_image($blob, $post, $transformed_value, &$out) {				// needs $out['terms']
		global $DCAPI;
		global $DCAPI_index;

		$image = $DCAPI->handle_image($blob, $post, $transformed_value, $out);			// referenced image on post first (if any)
		if (!$image) {	
			$termList = [];			// list organized by level
			$maxLevel = 0;
			if (isset($out['terms'])) foreach ($out['terms'] as $term) {
				$t = $term['term_id'];
				$l = intval($term['level']);
				if ($l > $maxLevel) $maxLevel = $l;
				$termList[$l][] = $t;
				foreach ($term['parentTerms'] as $pt) {
					$termList[--$l][] = $pt;
				}
			}
			$list = [];				// flattened list
			for ($i=$maxLevel; $i>=0; $i--) {
				if (isset($termList[$i])) foreach ($termList[$i] as $t) {
					$list[] = $t;
				}
			}
			foreach ($list as $t) {
				$image = ($DCAPI_index['termInfo'][$t]['categoryImage']);
				if ($image) break;														// return lowest level category image if no post image
			}
		}
		if (!$image) $image = $DCAPI->do_image(\get_field('default-post-image', 'option'));	// return default post image if no post or category image
		return $image;
	}	
	$DCAPI->register_transformation('handle post image', 'handle_post_image');

	function handle_page_image($blob, $post, $transformed_value, &$out) {
		global $DCAPI;
		$image = $DCAPI->handle_image($blob, $post, $transformed_value, $out);
		if (!$image) $image = $DCAPI->do_image(\get_field('default-page-image', 'option'));			// return default page image if no page image
		return $image;
	}	
	$DCAPI->register_transformation('handle page image', 'handle_page_image');

	function handle_person_image($blob, $post, $transformed_value, &$out) {
		global $DCAPI;
		$image = $DCAPI->handle_image($blob, $post, $transformed_value, $out);
		if (!$image) $image = $DCAPI->do_image(\get_field('default-portrait-image', 'option'));			// return default person image if no person image
		return $image;
	}	
	$DCAPI->register_transformation('handle person image', 'handle_person_image');

	function compare_menu_order($a, $b) {
		if ($a['feedData']['menu_order'] == $b['feedData']['menu_order']) {
			return 0;
		}
		return ($a['feedData']['menu_order'] < $b['feedData']['menu_order']) ? -1 : 1;
	}

	function handle_hierarchy($blob, $post, $transformed_value, &$out) {
		global $DCAPI_blob_config;
		global $DCAPI_index;
		global $DCAPI_items;

		$pageInfo = [];												// gather page information about categories/terms to build a category to page map
		$menu_list = [];

		$prefix = $DCAPI_blob_config['postTypePrefix']['page'];
		$postInfo = $DCAPI_index['postInfo'];
		$pageItems = $DCAPI_items[$prefix]->get_all();
		uasort($pageItems, 'compare_menu_order');					// sort pageItems list by menu_order

	// first do the hierarchy
		// list pages and provide the hierarchy (parent and immediate children): key is (page) ID

		$blob = $DCAPI_blob_config['blobs'][ $DCAPI_blob_config['blobTypeMap']['home'] ];
		$prefix = $blob['prefix'];
		$style = $blob['style'];
		$fixedID = $blob['fixedID'];
		if ( ($prefix) and ($style == 'fixedID') ) $home_blob_tag = $prefix . '/' . $fixedID;

		if ($home_blob_tag) {
			$pg = $pageItems[$fixedID];

			$menu_list[$fixedID] = array(							// homepage (page "0")
				'slug' => null,
				'title' => $pg['title'],
				'shortTitle' => $pg['shortTitle'],
				'feed' => $pg['feedType'],
				'children' => array(),
				'parent' => '$root',								// signal top of the tree
				);
			$pageInfo[0]['termList'] = $menu_list[$fixedID]['termList'];
			array_filter($menu_list[$fixedID]);
		}

		$blob = $DCAPI_blob_config['blobs'][ $DCAPI_blob_config['blobTypeMap']['page'] ];
		$prefix = $blob['prefix'];
		$surl = get_site_url();
		foreach ($pageItems as $ID => $pg) {
			if ($ID == 'meta') continue;
			$pl = get_permalink($ID);
			$temp = explode($surl, $pl);
			$fs = trim($temp[1], '/');			// full slug
			$s = explode('/', $fs);			
			$sl = $s[count($s)-1]; 				// slug
			$menu_list[$ID] = array(
				'slug' => $sl,
				'fullSlug' => $fs,
				'title' => $pg['title'],
				'shortTitle' => $pg['shortTitle'],
				'feed' => $pg['feedType'],
				'children' => [],
				'parent' => ($postInfo[$ID]['parentPage']) ? $postInfo[$ID]['parentPage'] : ( ($home_blob_tag) ? intval($fixedID) : '$root' ),
				);
			$pageInfo[$ID]['termList'] = $menu_list[$ID]['termList'];
		}		

		foreach ($menu_list as $key => $value) {
			$c = array(
				'slug' => $menu_list[$key]['slug'],
				'fullSlug' => $menu_list[$key]['fullSlug'],
				'title' => $menu_list[$key]['title'],
				'shortTitle' => $menu_list[$key]['shortTitle'],
				);
			$c = array_filter($c);
			$c['ID'] = $key;
			$menu_list[$value['parent']]['children'][] = $c;
		}
		foreach ($menu_list as $key => $value) {
			$p = $menu_list[$key]['parent'];
			$menu_list[$key] = array_filter($menu_list[$key]);			// clean out empty array elements
			$menu_list[$key]['parent'] = $p;							// preserve parent setting
		}
		unset($menu_list['$root']['parent']);							// clean up top of tree
		unset($menu_list['']);

		$out['hierarchy'] = $menu_list;
	}

	$DCAPI->register_transformation('create hierarchy', 'handle_hierarchy');
}