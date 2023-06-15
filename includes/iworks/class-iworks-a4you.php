<?php
/*

Copyright 2023-PLUGIN_TILL_YEAR (marcin@iworks.pl)

this program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( class_exists( 'iworks_a4you' ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ) . '/iworks.php' );

class iworks_a4you extends iworks {

	private $capability;

	private $gtag = array(
		'config' => array(),
		'js'     => array(
			'new Date()' => array(),
		),
		'set'    => array(),
		'event'  => array(),
	);

	public function __construct() {
		parent::__construct();
		$this->version    = 'PLUGIN_VERSION';
		$this->capability = apply_filters( 'iworks_a4you_capability', 'manage_options' );
		/**
		 * hooks
		 */
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'wp_head', array( $this, 'add_code' ), 0 );
		add_action( 'wp_head', array( $this, 'action_wp_head_add_common_events' ), PHP_INT_MAX );
		add_action( 'wp_footer', array( $this, 'action_wp_footer_maybe_print' ), PHP_INT_MAX );
		add_action( 'shutdown', array( $this, 'maybe_dev_mode_debug' ), PHP_INT_MAX );
		add_action( 'wp_login', array( $this, 'event_login' ) );
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
		/**
		 * Add event
		 *
		 * @since 1.0.0
		 *
		 * @param string $event
		 * @param array $params
		 */
		add_action( 'a4you_add_event', array( $this, 'add_event' ), 10, 2 );
		add_action( 'a4you_add_event', array( $this, 'add_event' ), 10, 3 );
		add_action( 'a4you_add_gtag', array( $this, 'add_gtag' ), 10, 2 );
		add_action( 'a4you_add_gtag', array( $this, 'add_gtag' ), 10, 3 );
	}

	public function init() {
		iworks_a4you_options_init();
		global $iworks_a4you_options;
		$this->options = $iworks_a4you_options;
	}

	public function admin_init() {
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Plugin row data
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( $this->dir . '/a4you.php' == $file ) {
			if ( ! is_multisite() && current_user_can( $this->capability ) ) {
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					add_query_arg(
						'page',
						'iworks_a4you_index',
						admin_url( 'options-general.php' )
					),
					__( 'Settings', 'a4you' )
				);
			}
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				add_query_arg(
					array(
						'utm_source' => 'a4you',
						'utm_medium' => 'plugin-row',
					),
					'https://ko-fi.com/iworks'
				),
				esc_html__( 'Donate', 'a4you' )
			);
		}
		return $links;
	}

	public function add_code() {
		/**
		 * maybe not?
		 */
		if ( ! $this->should_it_be_used() ) {
			return;
		}
		$tag_id = $this->options->get_option( 'tag_id' );
		if ( empty( $tag_id ) ) {
			return;
		}
		/**
		 * config
		 */
		$this->gtag['config'][ $tag_id ] = array();
		if ( $this->options->get_option( 'debug' ) ) {
			$this->gtag['config'][ $tag_id ] = array(
				'debug_mode' => true,
			);
		}
		echo PHP_EOL,'<!-- PLUGIN_NAME (PLUGIN_VERSION) -->',PHP_EOL;
		echo '<!-- Google tag (gtag.js) -->',PHP_EOL;
		printf(
			'<script async src="%s"></script>%s',
			esc_url(
				add_query_arg(
					'id',
					$tag_id,
					'https://www.googletagmanager.com/gtag/js'
				)
			),
			PHP_EOL
		);
		echo '<script>',PHP_EOL;
		echo 'window.dataLayer = window.dataLayer || [];',PHP_EOL;
		echo 'function gtag(){dataLayer.push(arguments);}',PHP_EOL;
		/**
		 * apply_filters
		 */
		foreach ( $this->gtag as $type => $value ) {
			$this->gtag[ $type ] = apply_filters( 'iworks_a4you_array_' . $type, $value );
		}
		/**
		 * config & js
		 */
		foreach ( array( 'config', 'js' ) as $type ) {
			foreach ( $this->gtag[ $type ] as $key => $params ) {
				$this->print_one_gtag( $type, $key, $params );
			}
		}
		/**
		 * set
		 */
		if ( ! empty( $this->gtag['set'] ) ) {
			printf( "gtag('set', %s);", json_encode( $this->gtag['set'] ) );
			echo PHP_EOL;
		}
		echo '</script>',PHP_EOL;
	}

	public function should_it_be_used() {
		if ( is_user_logged_in() ) {
			return ! empty( $this->options->get_option( 'is_user_logged_in' ) );
		}
		return true;
	}

	/**
	 * Add own event
	 *
	 * @since 1.0.0
	 *
	 * @param string $event
	 * @param array $params
	 */
	public function add_event( $event, $params = array() ) {
		$this->add_gtag(
			'event',
			$event,
			$params
		);
	}

	public function add_gtag( $type, $event, $params = array() ) {
		/**
		 * normalize type
		 */
		if ( ! in_array( $type, array_keys( $this->gtag ) ) ) {
			$type = 'event';
		}
		$this->gtag[ $type ][ $event ] = $params;
	}

	public function action_wp_head_add_common_events() {
		/**
		 * maybe not?
		 */
		if ( ! $this->should_it_be_used() ) {
			return;
		}
		/**
		 * 404?
		 */
		elseif ( apply_filters( 'iworks_a4you_is_404', is_404() ) ) {
			$this->add_event(
				'select_content',
				array(
					'content_type' => 'http_error',
					'content_id'   => 404,
				)
			);
		}
		/**
		 * is search?
		 */
		elseif ( apply_filters( 'iworks_a4you_is_search', is_search() ) ) {
			$params        = array(
				'search_term' => get_search_query(),
			);
			$search_params = apply_filters( 'iworks_a4you_event_search_params', array() );
			foreach ( $search_params as $param_key ) {
				$value = filter_input( INPUT_GET, $param_key );
				if ( empty( $value ) ) {
					$value = filter_input( INPUT_POST, $param_key );
				}
				if ( empty( $value ) ) {
					continue;
				}
				$params[ $param_key ] = $value;
			}
			$this->add_event( 'search', $params );
		}
		/**
		 * single content
		 */
		elseif ( apply_filters( 'iworks_a4you_is_singular', is_singular() ) ) {
			$this->add_event(
				'select_content',
				array(
					'content_type'  => get_post_type(),
					'content_id'    => get_the_ID(),
					'content_title' => get_the_title(),
				)
			);
		}
		/**
		 * is_category
		 */
		elseif ( apply_filters( 'iworks_a4you_is_archive', is_archive() ) ) {
			$queried_object = get_queried_object();
			/**
			 * taxonomy
			 */
			if ( is_a( $queried_object, 'WP_Term' ) ) {
				$this->add_event(
					'select_content',
					array(
						'content_type' => $queried_object->taxonomy,
						'content_id'   => $queried_object->cat_name,
					)
				);
			}
			/**
			 * date
			 */
			elseif ( is_date() ) {
				if ( is_year() ) {
					$this->add_event(
						'select_content',
						array(
							'content_type'    => 'date',
							'content_subtype' => 'year',
							'content_id'      => get_query_var( 'year' ),
						)
					);
				} elseif ( is_month() ) {
					$this->add_event(
						'select_content',
						array(
							'content_type'    => 'date',
							'content_subtype' => 'month',
							'content_id'      => sprintf(
								'%d-%02d',
								get_query_var( 'year' ),
								get_query_var( 'monthnum' )
							),
						)
					);
				} elseif ( is_day() ) {
					$this->add_event(
						'select_content',
						array(
							'content_type'    => 'date',
							'content_subtype' => 'day',
							'content_id'      => sprintf(
								'%d-%02d-%02d',
								get_query_var( 'year' ),
								get_query_var( 'monthnum' ),
								get_query_var( 'day' )
							),
						)
					);
				}
			}
			/**
			 * is_author
			 */
			elseif ( is_author() ) {
				$this->add_event(
					'select_content',
					array(
						'content_type' => 'author',
						'content_id'   => get_query_var( 'author_name' ),
					)
				);
			}
		}
		/**
		 * logged user
		 *
		 * @since 1.0.0
		 */
		if ( is_user_logged_in() ) {
			if ( $this->options->get_option( 'user_role' ) ) {
				$user = wp_get_current_user();
				foreach ( $user->roles as $role ) {
					$this->add_event(
						'user',
						array(
							'role' => $role,
						)
					);
				}
			} else {
				$this->add_event( 'user', array( 'role' => 'unknown' ) );
			}
		} else {
			$this->add_event( 'user', array( 'role' => 'not-logged' ) );
		}
	}

	public function action_wp_footer_maybe_print() {
		$type = 'event';
		$data = apply_filters( 'iworks_a4you_array_' . $type, $this->gtag[ $type ] );
		/**
		 * have I smth to show?
		 */
		if ( empty( $data ) ) {
			return;
		}
		$add_debug = $this->options->get_option( 'debug' );
		echo PHP_EOL,'<!-- PLUGIN_NAME (PLUGIN_VERSION) -->',PHP_EOL;
		echo '<script>',PHP_EOL;
		foreach ( $data as $key => $config ) {
			if ( $add_debug ) {
				$config['debug_mode'] = true;
			}
			$this->print_one_gtag( $type, $key, $config );
		}
		echo '</script>',PHP_EOL;
	}

	private function print_one_gtag( $type, $key, $params = array() ) {
		$patern = 'gtag("%s", "%s", %s);';
		if ( empty( $params ) ) {
			$patern = 'gtag("%s", "%s");';
		}
		if ( 'js' === $type ) {
			$patern = 'gtag("%s", %s, %s);';
			if ( empty( $params ) ) {
				$patern = 'gtag("%s", %s);';
			}
		}
		printf( $patern, $type, $key, json_encode( $params ) );
		echo PHP_EOL;
	}

	public function event_login() {
		$this->add_event( 'login', array( 'method' => 'WordPress' ) );
	}

	/**
	 * maybe dev debug?
	 */
	public function maybe_dev_mode_debug() {
		if ( defined( 'IWORKS_DEV_MODE' ) && IWORKS_DEV_MODE ) {
			echo '<div class="iworks-a4you-debug" style="max-width:600px;margin:0 auto">';
			foreach ( $this->gtag as $type => $values ) {
				if ( empty( $values ) ) {
					continue;
				}
				printf( '<h4 class="iworks-a4you-debug-title">%s</h4>', $type );
				echo '<table class="iworks-a4you-debug-table" style="border: 1px solid black">';
				foreach ( $values as $key => $params ) {
					echo '<tr>';
					printf( '<td class="iworks-a4you-debug-table-key">%s</td>', $key );
					if ( empty( $params ) ) {
						$params = array();
					}
					printf( '<td class="iworks-a4you-debug-table-params">%s</td>', json_encode( $params ) );
					echo '</tr>';
				}
				echo '</table>';
			}
			echo '</div>';
		}
	}

	public function action_plugins_loaded() {
		$dir = dirname( __FILE__ ) . '/a4you';
		/**
		 * WooCommerce
		 *
		 * @since 1.0.0
		 */
		if (
			defined( 'WC_PLUGIN_FILE' )
			&& defined( 'WC_VERSION' )
		) {
			/**
			 * Check minimal WooCommerce version to run.
			 *
			 * @since 1.0.0
			 *
			 */
			if ( version_compare( WC_VERSION, '5.5', '>' ) ) {
				include_once $dir . '/integration/class-iworks-a4you-integration-woocommerce.php';
				$this->objects['woocommerce'] = new iworks_a4you_integration_woocommerce();
			}
		}
		/**
		 * Debug Bar
		 *
		 * @since 1.0.0
		 */
		if ( isset( $GLOBALS['debug_bar'] ) ) {
			// include_once $dir . '/integration/class-iworks-a4you-integration-debug-bar.php';
			// $this->objects['debug-bar'] = new iworks_a4you_integration_debug_bar();
		}
		/**
		 * a4you loaded action
		 *
		 * @since 1.0.0
		 */
		do_action( 'a4you/loaded' );
	}


}