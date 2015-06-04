<?php
/*
Plugin Name: Ampersand Mobile SlideAd
Description: Add a slideup ad box listing a random selection of chosen posts.
Version: 1.0.2
Author: Richard Holmes
Author URI: https://ampersandstudio.uk/
License: GPL v2 or higher
License URI: License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'WP_AMSA' ) ) {
	
	class WP_AMSA {

		public function __construct() {
			// called just before the template functions are included
			add_action( 'init', array( $this, 'include_template_functions' ), 20 );
			
			// called after all plugins have loaded
			add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
			
			// add to header
			add_action( 'wp_head', array( $this, 'add_to_header' ) );
			
			// add to footer
			add_action( 'wp_footer', array( $this, 'add_to_footer' ) );
			
			// add meta boxes to posts
			add_filter( 'cmb_meta_boxes', array( $this, 'amsa_sample_metaboxes' ) );
			
			//set up ajax call to fill slidead div
			add_action( 'wp_ajax_nopriv_get_amsa_data', array( $this, 'amsa_ajax_action_callback' ) );
			add_action( 'wp_ajax_get_amsa_data', array( $this, 'amsa_ajax_action_callback' ) );
			
			// indicates we are running the admin
			if ( is_admin() ) {
			  // add setting submenu
			  add_action( 'admin_menu', array( $this, 'add_admin_settings' ) );
			  //call register settings function
			  add_action( 'admin_init', array( $this, 'register_admin_settings' ) );

			}
		
			// indicates we are being served over ssl
			if ( is_ssl() ) {
			  // ...
			}
		
		// take care of anything else that needs to be done immediately upon plugin instantiation, here in the constructor
		}
		
		/**
		* Take care of anything that needs to be loaded on init
		*/
		public function include_template_functions() {
			if ( ! class_exists( 'cmb_Meta_Box' ) )
				require_once(plugin_dir_path( __FILE__ ) . 'init.php');
		}
		
		/**
		* Take care of anything that needs all plugins to be loaded
		*/
		public function plugins_loaded() {
			// ...
		}
		
		/**
		* Take care of anything that needs adding to the header
		*/
		public function add_to_header() {
			// ...
		}
		
		/**
		* Take care of anything that needs adding to the footer
		*/
		public function add_to_footer() {
			// ...
			wp_register_style( 'ampersand-mobile-slidead-styles', plugins_url("/css/amsa.css", __FILE__) );
			wp_enqueue_style( 'ampersand-mobile-slidead-styles' );
			
			wp_enqueue_script( 'ampersand-mobile-slidead-scripts', plugins_url("/js/amsa.js", __FILE__), array( 'jquery' ) );
			wp_localize_script( 'ampersand-mobile-slidead-scripts', 'AmsaAjax', array(
			    // URL to wp-admin/admin-ajax.php to process the request
			    'ajaxurl' => admin_url( 'admin-ajax.php' ),
			 
			    // generate a nonce with a unique ID "myajax-post-comment-nonce"
			    // so that you can check it later when an AJAX request is sent
			    'amsaMobileNonce' => wp_create_nonce( 'amsa-mobile-nonce' )
			    )
			);
		}
		
		static function amsa_sample_metaboxes( $meta_boxes ) {
			$prefix = '_amsa_'; // Prefix for all fields
		
			$meta_boxes[] = array(
				'id' => 'show_mobile_slidead',
				'title' => 'Show in Mobile SlideAd?',
				'pages' => array('post'), // post type
				'context' => 'normal',
				'priority' => 'low',
				'show_names' => true, // Show field names on the left
				'fields' => array(
					array(
						'name' => __( 'Show?', 'cmb' ),
						'desc' => __( 'Tick if you would like this to be included in the randomised selection for posts in the mobile slidead', 'cmb' ),
						'id'   => $prefix . 'show_mobile_slidead',
						'type' => 'checkbox',
					),
				),
			);
		
			return $meta_boxes;
		}
		
		/**
		* Take care of the settings that can be set
		*/
		public function register_admin_settings() {
			//register our settings
			register_setting( 'amsa-settings-group', 'amsa_posts_to_show' );
			register_setting( 'amsa-settings-group', 'amsa_box_title' );
		}
		
		/**
		* Take care of the settings link
		*/
		public function add_admin_settings() {
			// ...
			add_options_page( 'Mobile SlideAd Options', 'Mobile SlideAd', 'manage_options', 'amsa-settings-group', array( $this, 'amsa_admin_options' ) );
		}
		
		/**
		* Take care of the settings link
		*/
		public function amsa_admin_options() {
			if ( !current_user_can( 'manage_options' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}
			
			
			echo '<div class="wrap">';
			echo '<h2>Mobile SlideAd</h2>';
			echo '<form method="post" action="options.php">';
			
			settings_fields( 'amsa-settings-group' );
			do_settings_sections( 'amsa-settings-group' );
			
			echo '<table class="form-table">';
			echo '	<tr valign="top">';
			echo '        <th scope="row">How Many Posts to Display</th>';
			echo '        <td><input type="text" name="amsa_posts_to_show" value="'.esc_attr( get_option('amsa_posts_to_show') ).'" /></td>';
			echo '  </tr>';
			echo '	<tr valign="top">';
			echo '        <th scope="row">SlideAd Box Title</th>';
			echo '        <td><input type="text" name="amsa_box_title" value="'.esc_attr( get_option('amsa_box_title') ).'" /></td>';
			echo '  </tr>';
			echo '</table>';
			
			submit_button();
			
			echo '</form>';
			echo '</div>';
		}
		
		/**
		* Get content for the slidead div
		*/
		public function amsa_ajax_action_callback() {
			
			$nonce = $_POST['amsaMobileNonce'];
 
		    // check to see if the submitted nonce matches with the
		    // generated nonce we created earlier
		    if ( ! wp_verify_nonce( $nonce, 'amsa-mobile-nonce' ) )
		        die ( 'Busted!');
        
			global $wpdb; // this is how you get access to the database
		
			$limit = get_option('amsa_posts_to_show');
			$myquery = new WP_Query( "meta_key=_amsa_show_mobile_slidead&meta_value=on&orderby=rand&posts_per_page=".(!empty($limit) ? $limit : 1) );
			if ($myquery->have_posts()) {
				echo '<h5>' . get_option('amsa_box_title') . '</h5>';
				while ( $myquery->have_posts() ) {
					$myquery->the_post();
					echo '<div class="amsa-post-wrapper">';
					if ( has_post_thumbnail() ) { // check if the post has a Post Thumbnail assigned to it.
						echo '<a href="';
						the_permalink();
						echo '">';
						the_post_thumbnail( array(100) );
						echo '</a>';
					} 
					echo '<a href="';
					the_permalink();
					echo '">';
					echo '<h6>' . get_the_title() . '</h6>';
					echo '</a>';
					echo '</div>';
					echo '<div class="amsa-clear"></div>';
				}
			}
			else {
				echo "false";
			}
			wp_reset_postdata();
		
			wp_die(); // this is required to terminate immediately and return a proper response
		}
	}
	 
	// finally instantiate our plugin class and add it to the set of globals
	 
	$GLOBALS['wp_amsa'] = new WP_AMSA();
}