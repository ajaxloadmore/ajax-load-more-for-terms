<?php
/**
 * Plugin Name: Ajax Load More for Terms
 * Plugin URI: http://connekthq.com/plugins/ajax-load-more/extensions/terms/
 * Description: An Ajax Load More extension that adds compatibility for loading taxonomy terms via Ajax.
 * Text Domain: ajax-load-more-for-terms
 * Author: Darren Cooney
 * Author URI: https://connekthq.com
 * Version: 1.1.1
 * License: GPL
 * Copyright: Connekt Media & Darren Cooney
 *
 * @package ajax-load-more-for-terms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *  Installation hook.
 */
function alm_terms_install() {
	if ( ! is_plugin_active( 'ajax-load-more/ajax-load-more.php' ) ) {
		set_transient( 'alm_terms_admin_notice', true, 5 );
	}
}
register_activation_hook( __FILE__, 'alm_terms_install' );

/**
 * Display admin notice if plugin does not meet the requirements.
 */
function alm_terms_admin_notice() {
	$slug = 'ajax-load-more';
	// Ajax Load More Notice.
	if ( get_transient( 'alm_terms_admin_notice' ) ) {
		$install_url = get_admin_url() . '/update.php?action=install-plugin&plugin=' . $slug . '&_wpnonce=' . wp_create_nonce( 'install-plugin_' . $slug );
		$message     = '<div class="error">';
		$message    .= '<p>You must install and activate the core Ajax Load More plugin before using the Ajax Load More Terms extension.</p>';
		$message    .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, 'Install Ajax Load More Now' ) . '</p>';
		$message    .= '</div>';
		echo wp_kses_post( $message );
		delete_transient( 'alm_terms_admin_notice' );
	}
}
add_action( 'admin_notices', 'alm_terms_admin_notice' );

define( 'ALM_TERMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALM_TERMS_URL', plugins_url( '', __FILE__ ) );

if ( ! class_exists( 'ALM_TERMS' ) ) :

	/**
	 * Initiate the class.
	 */
	class ALM_TERMS {

		/**
		 * Construct class.
		 *
		 * @author ConnektMedia <darren@connekthq.com>
		 * @since 1.0
		 */
		public function __construct() {
			add_action( 'alm_terms_installed', [ &$this, 'alm_terms_installed' ] );
			add_filter( 'alm_terms_shortcode', [ &$this, 'alm_terms_shortcode' ], 10, 6 );
			add_filter( 'alm_terms_preloaded', [ &$this, 'alm_terms_preloaded_query' ], 10, 4 );
			add_action( 'wp_ajax_alm_get_terms', [ &$this, 'alm_get_terms_query' ] );
			add_action( 'wp_ajax_nopriv_alm_get_terms', [ &$this, 'alm_get_terms_query' ] );
		}

		/**
		 *  Preloaded Query for Terms.
		 *
		 * @param array  $args             The query args.
		 * @param string $preloaded_amount The amount to preload.
		 * @param string $repeater         The current Repeater Template name.
		 * @param string $theme_repeater   The current Theme Repeater name.
		 * @since 1.0
		 */
		public function alm_terms_preloaded_query( $args, $preloaded_amount, $repeater, $theme_repeater ) {
			$id                    = isset( $args['id'] ) ? sanitize_text_field( $args['id'] ) : '';
			$offset                = isset( $args['offset'] ) ? sanitize_text_field( $args['offset'] ) : 0;
			$preloaded_amount      = isset( $preloaded_amount ) ? $preloaded_amount : $args['term_query_number'];
			$term_query            = isset( $args['term_query'] ) ? $args['term_query'] : false;
			$term_query_taxonomy   = isset( $term_query['taxonomy'] ) ? sanitize_text_field( trim( $term_query['taxonomy'] ) ) : '';
			$term_query_hide_empty = isset( $term_query['hide_empty'] ) ? sanitize_text_field( $term_query['hide_empty'] ) : true;
			$term_query_hide_empty = $term_query_hide_empty === 'false' ? false : true;
			$term_query_number     = isset( $term_query['number'] ) ? sanitize_text_field( $term_query['number'] ) : '5';
			$term_query            = empty( $term_query_taxonomy ) ? false : $term_query;

			if ( $term_query ) {
				$args = [
					'taxonomy'   => explode( ',', $term_query_taxonomy ),
					'number'     => $term_query_number,
					'hide_empty' => $term_query_hide_empty,
					'offset'     => $offset,
				];

				/**
				 * ALM Term Query Filter Hook
				 *
				 * @return array
				 */
				$args = apply_filters( 'alm_term_query_args_' . $id, $args );

				// WP_Term_Query.
				$alm_term_query = new WP_Term_Query( $args );

				// Set ALM Variables.
				$alm_found_posts = $this->alm_terms_count( $args, $offset );
				$alm_page        = 0;
				$alm_item        = 0;
				$alm_current     = 0;
				$data            = '';

				if ( $alm_term_query->terms ) {
					ob_start();
					foreach ( $alm_term_query->terms as $term ) {
						++$alm_item;
						++$alm_current;
						if ( $theme_repeater !== 'null' && has_action( 'alm_get_term_query_theme_repeater' ) ) {
							// Theme Repeater.
							do_action( 'alm_get_term_query_theme_repeater', $theme_repeater, $alm_found_posts, $alm_page, $alm_item, $alm_current, $term );
						} else {
							// Repeater.
							include alm_get_current_repeater( $repeater, $type );
						}
					}
					$data = ob_get_clean();
				}

				return [
					'data'  => $data,
					'total' => $alm_found_posts,
				];
			}
		}

		/**
		 * Ajax Query terms and return data to ALM
		 *
		 * @since 1.0
		 */
		public function alm_get_terms_query() {
			$form_data       = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );
			$data            = isset( $form_data['term_query'] ) ? $form_data['term_query'] : '';
			$id              = isset( $form_data['id'] ) ? sanitize_text_field( $form_data['id'] ) : '';
			$repeater        = isset( $form_data['repeater'] ) ? sanitize_text_field( $form_data['repeater'] ) : 'default';
			$type            = alm_get_repeater_type( $repeater );
			$theme_repeater  = isset( $form_data['theme_repeater'] ) ? sanitize_text_field( $form_data['theme_repeater'] ) : 'null';
			$page            = isset( $form_data['page'] ) ? (int) $form_data['page'] : 1;
			$offset          = isset( $form_data['offset'] ) ? (int) $form_data['offset'] : 0;
			$original_offset = $offset;
			$canonical_url   = isset( $form_data['canonical_url'] ) ? esc_url( $form_data['canonical_url'] ) : $_SERVER['HTTP_REFERER'];
			$query_type      = isset( $form_data['query_type'] ) ? sanitize_text_field( $form_data['query_type'] ) : 'standard';

			// Cache Add-on.
			$cache_id        = isset( $form_data['cache_id'] ) ? sanitize_text_field( $form_data['cache_id'] ) : '';
			$cache_slug      = isset( $form_data['cache_slug'] ) && $form_data['cache_slug'] ? sanitize_text_field( $form_data['cache_slug'] ) : '';
			$cache_logged_in = isset( $form_data['cache_logged_in'] ) ? sanitize_text_field( $form_data['cache_logged_in'] ) : false;
			$do_create_cache = $cache_logged_in === 'true' && is_user_logged_in() ? false : true;

			// Preloaded Add-on.
			$preloaded        = isset( $form_data['preloaded'] ) ? sanitize_text_field( $form_data['preloaded'] ) : false;
			$preloaded_amount = 0;
			if ( has_action( 'alm_preload_installed' ) && $preloaded === 'true' ) {
				$preloaded_amount = isset( $form_data['preloaded_amount'] ) ? sanitize_text_field( $form_data['preloaded_amount'] ) : '5';
				$offset           = $offset + $preloaded_amount;
			}

			// Get Term Data.
			if ( $data ) {
				$term_query            = isset( $data['term_query'] ) ? sanitize_text_field( $data['term_query'] ) : false;
				$term_query_taxonomy   = isset( $data['taxonomy'] ) ? sanitize_text_field( trim( $data['taxonomy'] ) ) : '';
				$term_query_hide_empty = isset( $data['hide_empty'] ) ? sanitize_text_field( $data['hide_empty'] ) : true;
				$term_query_hide_empty = $term_query_hide_empty === 'false' ? false : true;
				$term_query_number     = isset( $data['number'] ) ? sanitize_text_field( $data['number'] ) : '5';
				$term_query            = empty( $term_query_taxonomy ) ? false : $term_query;
				$offset                = $offset + ( $term_query_number * $page );

				if ( $term_query ) {
					$args = [
						'taxonomy'   => explode( ',', $term_query_taxonomy ),
						'hide_empty' => $term_query_hide_empty,
						'number'     => $term_query_number,
						'offset'     => $offset,
					];

					/**
					 * ALM Term Query Filter Hook
					 *
					 * @return $args;
					 */
					$args = apply_filters( 'alm_term_query_args_' . $id, $args );

					// WP_Term_Query.
					$alm_term_query = new WP_Term_Query( $args );

					if ( $query_type === 'totalposts' ) {
						// Paging add-on.
						$return = [
							'totalposts' => $this->alm_terms_count( $args, $original_offset ),
						];

					} else {
						// Standard ALM.
						if ( $alm_term_query->terms ) {

							// Set ALM Variables.
							$alm_found_posts = $this->alm_terms_count( $args, $original_offset );
							$alm_post_count  = count( $alm_term_query->terms );
							$alm_current     = 0;

							ob_start();
							foreach ( $alm_term_query->terms as $term ) {
								++$alm_current; // Current item in loop.
								$alm_page = $page + 1; // Get page number.
								$alm_item = ( $alm_page * $term_query_number ) - $term_query_number + $alm_current + $preloaded_amount;
								if ( $theme_repeater !== 'null' && has_action( 'alm_get_term_query_theme_repeater' ) ) {
									// Theme Repeater.
									do_action( 'alm_get_term_query_theme_repeater', $theme_repeater, $alm_found_posts, $alm_page, $alm_item, $alm_current, $term );
								} else {
									// Repeater.
									include alm_get_current_repeater( $repeater, $type );
								}
							}
							$data = ob_get_clean();

							$return = [
								'html' => $data,
								'meta' => [
									'postcount'  => $alm_post_count,
									'totalposts' => $alm_found_posts,
								],
							];

							/**
							 * Cache Add-on hook
							 * If Cache is enabled, check the cache file
							 *
							 * @return void
							 */
							if ( $cache_id && method_exists( 'ALMCache', 'create_cache_file' ) && $do_create_cache ) {
								ALMCache::create_cache_file( $cache_id, $cache_slug, $canonical_url, $data, $alm_post_count, $alm_found_posts );
							}
						} else {
							// No Results.
							$return = [
								'html' => null,
								'meta' => [
									'postcount'  => 0,
									'totalposts' => 0,
								],
							];
						}
					}
				}
			}

			wp_send_json( $return );
		}

		/**
		 * Get a full count of the available terms.
		 *
		 * @param  array  $args   The query args.
		 * @param  string $offset The offset count.
		 * @return int            The total terms count.
		 * @since 1.0
		 */
		public function alm_terms_count( $args, $offset ) {
			$count_args           = $args;
			$count_args['number'] = 99999;
			$count_args['offset'] = $offset;
			$count_args['fields'] = 'tt_ids';
			$term_query           = new WP_Term_Query( $count_args );
			return count( $term_query->terms );
		}

		/**
		 * Build Term Query shortcode params and send back to core ALM
		 *
		 * @param  string $term_query            The value for terms (true/false).
		 * @param  string $term_query_taxonomy   The term taxonomy.
		 * @param  string $term_query_hide_empty Query parameter.
		 * @param  string $term_query_number     Query parameter.
		 * @return string                        The generated HTML data attributes.
		 * @since 1.0
		 */
		public function alm_terms_shortcode( $term_query, $term_query_taxonomy, $term_query_hide_empty, $term_query_number ) {
			$return  = ' data-term-query="true"';
			$return .= ' data-term-query-taxonomy="' . $term_query_taxonomy . '"';
			$return .= ' data-term-query-hide-empty="' . $term_query_hide_empty . '"';
			$return .= ' data-term-query-number="' . $term_query_number . '"';
			return $return;
		}

		/**
		 * An empty function to determine if Terms is activated.
		 *
		 * @since 1.0
		 */
		public function alm_terms_installed() {
			// phpcs:ignore
			// Empty return.
		}
	}

	/**
	 * The main function responsible for returning the one true ALM_TERMS Instance.
	 *
	 * @since 1.0
	 */
	function alm_terms() {
		global $alm_terms;
		if ( ! isset( $alm_terms ) ) {
			$alm_terms = new ALM_TERMS();
		}
		return $alm_terms;
	}
	alm_terms();

endif;
