<?php
/*
Plugin Name: Ajax Load More for Terms
Plugin URI: http://connekthq.com/plugins/ajax-load-more/extensions/terms/
Description: An Ajax Load More extension that adds compatibility for loading taxonomy terms via Ajax.
Text Domain: ajax-load-more-for-terms
Author: Darren Cooney
Twitter: @KaptonKaos
Author URI: https://connekthq.com
Version: 1.0
License: GPL
Copyright: Darren Cooney & Connekt Media

Compatible with SEO, Paging, Cache and Preloaded


*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


define('ALM_TERMS_PATH', plugin_dir_path(__FILE__));
define('ALM_TERMS_URL', plugins_url('', __FILE__));



/*
*  alm_terms_install
*  Install the add-on
*
*  @since 1.0
*/

register_activation_hook( __FILE__, 'alm_terms_install' );
function alm_terms_install() {
   if(!is_plugin_active('ajax-load-more/ajax-load-more.php')){	//if Ajax Load More is activated
   	die('You must install and activate <a href="https://wordpress.org/plugins/ajax-load-more/">Ajax Load More</a> before installing the Term Query extension.');
	}
}



if(!class_exists('ALM_TERMS')) :

   class ALM_TERMS{

   	function __construct(){
   		add_action( 'alm_terms_installed', array(&$this, 'alm_terms_installed') );
   	   add_filter( 'alm_terms_shortcode', array(&$this, 'alm_terms_shortcode'), 10, 6 );
   		add_filter( 'alm_terms_preloaded', array(&$this, 'alm_terms_preloaded_query'), 10, 4 );
   	   add_action( 'wp_ajax_alm_get_terms', array(&$this, 'alm_get_terms_query') );
   	   add_action( 'wp_ajax_nopriv_alm_get_terms', array(&$this, 'alm_get_terms_query') );
      }



      /**
   	 *  alm_terms_preloaded
   	 *  Preloaded Query for Terms
   	 *
   	 *  @since 1.0
   	 */
   	public function alm_terms_preloaded_query($args, $preloaded_amount, $repeater, $theme_repeater){
	   	
         $id = (isset($args['id'])) ? sanitize_text_field( $args['id'] ) : '';
         $post_id = (isset($args['post_id'])) ? sanitize_text_field( $args['post_id'] ) : '';
         $offset = (isset($args['offset'])) ? sanitize_text_field( $args['offset'] ) : 0;
         $preloaded_amount = (isset($preloaded_amount)) ? $preloaded_amount : $args['term_query_number'];
         
      	$term_query = (isset($args['term_query'])) ? $args['term_query'] : false; //  We sanitize this later
      	$term_query_taxonomy = (isset($term_query['taxonomy'])) ? sanitize_text_field( trim($term_query['taxonomy']) ) : '';
			$term_query_hide_empty = (isset($term_query['hide_empty'])) ? sanitize_text_field( $term_query['hide_empty'] ) : true;
			$term_query_hide_empty = ($term_query_hide_empty === 'false') ? false : true;
			$term_query_number = (isset($term_query['number'])) ? sanitize_text_field( $term_query['number'] ) : '5';
			$term_query = (empty($term_query_taxonomy)) ? false : $term_query;
						
			if($term_query){
				
				$args = array(
					'taxonomy'	=> explode(',', $term_query_taxonomy),
					'number' => $term_query_number,					
					'hide_empty' => $term_query_hide_empty,
					'offset' => $offset
				);	
					
				/*
				 *	alm_term_query_args_{id}
				 *
				 * ALM Term Query Filter Hook
				 *
				 * @return $args;
				 */
			   $args = apply_filters('alm_term_query_args_'.$id, $args);						
				
				
				// WP_Term_Query
				$alm_term_query = new WP_Term_Query($args);
				
				// Set ALM Variables
				$alm_found_posts = $this->alm_terms_count($args, $offset);
				$alm_page = 0;
				$alm_item = 0;
				$alm_current = 0;
				$data = '';
				
				if($alm_term_query->terms){
					
					ob_start();						
	      		foreach ( $alm_term_query->terms as $term ) {		      		
		      		$alm_item++;
			         $alm_current++;  		      		
	               if($theme_repeater != 'null' && has_action('alm_get_term_query_theme_repeater')){
	               	// Theme Repeater
	               	do_action('alm_get_term_query_theme_repeater', $theme_repeater, $alm_found_posts, $alm_page, $alm_item, $alm_current, $term);
	               }else{
	               	// Repeater
	               	include(alm_get_current_repeater( $repeater, $type ));
	               }		      		
		      	}
		      	$data = ob_get_clean();		
		      			      	
            }		
				
				$results = array(
		         'data' => $data,
		         'total' => $alm_found_posts
	         );
	         
				return $results;		
			}
   	}



   	/**
   	 *  alm_get_terms_query
   	 *  Ajax Query terms and return data to ALM
   	 *
   	 *  @since 1.0
   	 */
   	function alm_get_terms_query(){

		   $data = (isset($_GET['term_query'])) ? $_GET['term_query'] : ''; // We sanitize this later
		   
		   $id = (isset($_GET['id'])) ? sanitize_text_field( $_GET['id'] ) : '';
         $post_id = (isset($_GET['post_id'])) ? sanitize_text_field( $_GET['post_id'] ) : '';
		   $repeater = (isset($_GET['repeater'])) ? sanitize_text_field( $_GET['repeater'] ) : 'default';
			$type = alm_get_repeater_type($repeater);
			$theme_repeater = (isset($_GET['theme_repeater'])) ? sanitize_text_field( $_GET['theme_repeater'] ) : 'null';
		   $posts_per_page = (isset($_GET['posts_per_page'])) ? sanitize_text_field( $_GET['posts_per_page'] ) : 5;
		   $page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;
		   $offset = (isset($_GET['offset'])) ? (int)$_GET['offset'] : 0;
		   $original_offset = $offset;
		   $canonical_url = (isset($_GET['canonical_url'])) ? esc_url( $_GET['canonical_url'] ) : $_SERVER['HTTP_REFERER'];
		   $queryType = (isset($_GET['query_type'])) ? sanitize_text_field( $_GET['query_type'] ) : 'standard'; // Ajax Query Type
		   
		   
		   // Cache Add-on
		   $cache_id = (isset($_GET['cache_id'])) ? sanitize_text_field( $_GET['cache_id'] ) : '';


		   // Preload Add-on
			$preloaded = (isset($_GET['preloaded'])) ? sanitize_text_field( $_GET['preloaded'] ) : false;
			$preloaded_amount = 0;
			if(has_action('alm_preload_installed') && $preloaded === 'true'){
				$preloaded_amount = (isset($_GET['preloaded_amount'])) ? sanitize_text_field( $_GET['preloaded_amount'] ) : '5';
      		$old_offset = $preloaded_amount;
      	   $offset = $offset + $preloaded_amount;
      	}


		   // SEO Add-on
			$seo_start_page = (isset($_GET['seo_start_page'])) ? sanitize_text_field( $_GET['seo_start_page'] ) : 1;


		   /**
			 *	alm_cache_create_dir
			 *
			 * Cache Add-on hook
			 * Create cache directory + meta .txt file
			 *
			 * @return null
			 */
		   if(!empty($cache_id) && has_action('alm_cache_create_dir')){
		      apply_filters('alm_cache_create_dir', $cache_id, $canonical_url);
		      $page_cache = '';
		   }

			
			// Get Term Data
		   if($data){

		      $term_query = (isset($data['term_query'])) ? sanitize_text_field( $data['term_query'] ) : false;
				$term_query_taxonomy = (isset($data['taxonomy'])) ? sanitize_text_field( trim($data['taxonomy']) ) : '';
				$term_query_hide_empty = (isset($data['hide_empty'])) ? sanitize_text_field( $data['hide_empty'] ) : true;
				$term_query_hide_empty = ($term_query_hide_empty === 'false') ? false : true;
				$term_query_number = (isset($data['number'])) ? sanitize_text_field( $data['number'] ) : '5';
				$term_query = (empty($term_query_taxonomy)) ? false : $term_query;
								
				$offset = $offset + ($term_query_number * $page);		

				if($term_query){								
					$args = array(
						'taxonomy'	=> explode(',', $term_query_taxonomy),
						'hide_empty' => $term_query_hide_empty,
						'number' => $term_query_number,
						'offset' => $offset
					);	
					
					
					/**
	   			 *	alm_term_query_args_{id}
	   			 *
	   			 * ALM Term Query Filter Hook
	   			 *
	   			 * @return $args;
	   			 */
	   		   $args = apply_filters('alm_term_query_args_'.$id, $args);						
					
					
					// WP_Term_Query
					$alm_term_query = new WP_Term_Query($args);
					
					
					if($queryType === 'totalposts') {						 
						// Paging add-on
						
						$return = array( 'totalposts' => $this->alm_terms_count($args, $original_offset) );
					
					} else {
						// Standard ALM
						
						if($alm_term_query->terms){
	   					
	   					// Set ALM Variables
	   					$alm_found_posts = $this->alm_terms_count($args, $original_offset);
	   					$alm_post_count = count($alm_term_query->terms);
	   					$alm_current = 0;
								   			
	   	      		ob_start();
	   	
	   	      		foreach ( $alm_term_query->terms as $term ) {
	      	      		
	      	      		$alm_current++; // Current item in loop
	   			         $alm_page = $page + 1; // Get page number
	   			         $alm_item = ($alm_page * $term_query_number) - $term_query_number + $alm_current + $preloaded_amount; // Get current item  
	                     if($theme_repeater != 'null' && has_action('alm_get_term_query_theme_repeater')){
	                     	// Theme Repeater
	                     	do_action('alm_get_term_query_theme_repeater', $theme_repeater, $alm_found_posts, $alm_page, $alm_item, $alm_current, $term);
	                     }else{
	                     	// Repeater
	                     	include(alm_get_current_repeater( $repeater, $type ));
	                     }
	   		      		
	   		      	}               
	   	            
	   	            $data = ob_get_clean();   					
	   
	   					$return = array(
	   		            'html' => $data,
	   		            'meta' => array(
	   		               'postcount'  => $alm_post_count,
	   		               'totalposts' => $alm_found_posts
	   		            )
	   		         );
	   		         
	   		         
	   		         /*
		                *	alm_cache_file
		                *
		                * Cache Add-on hook
		                * If Cache is enabled, check the cache file
		                *
		                * @return null
		                */
		               
		               if(!empty($data) && !empty($cache_id) && has_action('alm_cache_installed')){
		                  $cache_page = $page + 1;
		                  apply_filters('alm_nextpage_cache_file', $cache_id, $cache_page, $data);
		               }
	   		         
			         }
			         
			         else{ 
				         // No Results
			            $return = array(
	   		            'html' => null,
	   		            'meta' => array(
	   		               'postcount'  => 0,
	   		               'totalposts' => 0
	   		            )
	   		         );   		         
			         }
			      }
				}
			}
			
			wp_send_json($return);
			
   	}
   	
   	
   	
   	/**
   	 *  alm_terms_count
   	 *  Get a full count of the available terms
   	 *
   	 *  @return Term count
   	 *  @since 1.0
   	 */
   	public function alm_terms_count($args, $offset){
         $count_args = $args;
         $count_args['number'] = 99999;
         $count_args['offset'] = $offset;
         $count_args['fields'] = 'tt_ids';         
         $term_query = new WP_Term_Query($count_args);
         
         return count($term_query->terms);
   	}



   	/**
   	 *  alm_terms_shortcode
   	 *  Build Term Query shortcode params and send back to core ALM
   	 *
   	 *  @since 1.0
   	 */
   	function alm_terms_shortcode($term_query, $term_query_taxonomy, $term_query_hide_empty, $term_query_number){
   		$return  = ' data-term-query="true"';
   		$return .= ' data-term-query-taxonomy="'. $term_query_taxonomy .'"';
   		$return .= ' data-term-query-hide-empty="'. $term_query_hide_empty .'"';
	   	$return .= ' data-term-query-number="'. $term_query_number .'"';

		   return $return;
   	}
   	 
   	
   	
   	/*
   	*  alm_terms_installed
   	*  an empty function to determine if Terms is activated.
   	*
   	*  @since 1.0
   	*/
   	
   	function alm_terms_installed(){
   	   //Empty return
   	} 
   	
   	
   }



   /**
   *  ALM_TERMS
   *  The main function responsible for returning the one true ALM_TERMS Instance.
   *
   *  @since 1.0
   */
   function ALM_TERMS(){
      global $ALM_TERMS;

      if( !isset($ALM_TERMS) ){
         $ALM_TERMS = new ALM_TERMS();
      }

      return $ALM_TERMS;
   }
   ALM_TERMS(); // initialize

endif;
