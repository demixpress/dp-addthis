<?php

/*
 * Plugin Name: (dp) AddThis
 * Plugin URI: http://demixpress.com/plugins/dp-addthis/
 * Description: Makes it even easier and better to display AddThis tools (share buttons, follow buttons and recommended contents) on your WordPress site.
 * Version: 1.0.1
 * Author: DemixPress
 * Author URI: http://demixpress.com/
 * Requires: 4.1 or higher
 */

class DP_AddThis_Plugin {
	public $inline_tools_filters = array();
	public $inline_tools_filtered = false;
	public $has_tool = true;

	function __construct() {
		// admin
		if ( is_admin() ) {
			require_once( dirname( __FILE__ ) . '/admin/admin.php' );
			$dp_addthis_admin = new DP_AddThis_Admin();
		}

		// scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_async_attr' ), 10, 3 );

		add_action( 'template_redirect', array( $this, 'init' ), 10, 3 );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		register_activation_hook( __FILE__, array( $this, 'register_default_settings' ) );
	}

	/**
	 * Initialize.
	 *
	 * @return bool
	 */
	function init() {
		$pubid = get_option( 'dp_addthis_pubid' );

		if ( $pubid) {
			// enqueue scripts and styles
			if($this->has_tool())
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			add_action( 'wp_head', array( $this, 'enqueue_styles' ) );

			// inline tools
			add_action( 'wp_head', array( $this, 'prepare_inline_tools_filters' ) );
			add_action( 'loop_start', array( $this, 'handle_inline_tools' ) );

			// smart layers
			add_action( 'wp_head', array( $this, 'handle_smart_layers' ) );
		}

	}

	/**
	 * Whether the current page has any tools.
	 *
	 * @return bool
	 */
	function has_tool() {
		$all_rules        = dp_addthis_get_rules();
		$rule_contexts    = array_unique( wp_filter_object_list( $all_rules, '', 'and', 'context' ) );
		$current_contexts = dp_addthis_get_contexts();
		$has_contexts     = array_intersect( $rule_contexts, $current_contexts );

		return ! empty( $has_contexts );
	}

	/**
	 * Handle the display of inline tools.
	 *
	 * @since 1.0.0
	 */
	function handle_inline_tools( $query ) {
		$filters = $this->inline_tools_filters;

		if ( $query->is_main_query() && ! $this->inline_tools_filtered ) {
			foreach ( $filters as $filter ) {
				$filter->add();
			}
			$this->inline_tools_filtered = true;

		} elseif ( $this->inline_tools_filtered ) {
			foreach ( $filters as $filter ) {
				$filter->remove();
			}
			$this->inline_tools_filtered = false;
		}
	}

	/**
	 * Prepare inline tools filters to easier add/remove them later.
	 *
	 * @since 1.0.0
	 */
	function prepare_inline_tools_filters() {
		$rules    = dp_addthis_get_rules( 'inline_tools' );


		if(!empty($rules)) {
			$contexts = dp_addthis_get_contexts();

			foreach ( $rules as $rule ) {
				if ( in_array( $rule['context'], $contexts ) ) {
					array_push( $this->inline_tools_filters, new DP_AddThis_Inline_Tool_Filter( $rule ) );
				}
			}
		}
	}

	/**
	 * Handle the display of smart layers via CSS.
	 *
	 * @since 1.0.0
	 */
	function handle_smart_layers() {
		$css = '.addthis-smartlayers{display:none;}.dp-addthis .addthis-smartlayers{display:block;}';

		$rules    = dp_addthis_get_rules( 'smart_layers' );

		if(!empty($rules)) {

			$contexts = dp_addthis_get_contexts();
			$tools    = dp_addthis_get_tools( array( 'is_smartlayer' => true ) );

			foreach ( $rules as $rule ) {
				if ( ! in_array( $rule['context'], $contexts ) ) {
					continue;
				}

				$tool = $rule['tool'];

				if ( ! empty( $tools[ $tool ]['selector'] ) ) {
					$css .= $tools[ $tool ]['selector'] . '{display:block;}';
				}
			}
		}

		echo "\n<!-- Smart Layers CSS by (dp) AddThis START -->";
		echo "\n" . '<style type="text/css">' . $css . '</style>';
		echo "\n<!-- Smart Layers CSS by (dp) AddThis END -->\n";
	}

	/**
	 * Filter to add async attribute to script tag.
	 *
	 * @since 1.0.0
	 */
	function add_async_attr( $tag, $handle, $src ) {
		if ( $handle == 'dp-addthis' ) {
			$tag = str_replace( '></script>', 'async="async"></script>', $tag );
		}

		return $tag;
	}

	/**
	 * Register scripts for conditional script loading later.
	 *
	 * @since 1.0.0
	 */
	function register_scripts() {
		$pubid = get_option( 'dp_addthis_pubid' );
		if ( ! $pubid ) {
			return;
		}

		wp_register_script( 'dp-addthis', '//s7.addthis.com/js/300/addthis_widget.js#pubid=' . $pubid, array(), null, true );

		$addthis_config = $this->get_config();


		wp_localize_script( 'dp-addthis', 'addthis_config', $addthis_config );


//		$addthis_share = array(
//			'passthrough' => array(
//				'twitter' => array(
//					'via'  => 'yahoo',
//					'text' => 'text'
//				)
//			)
//		);
//
//		wp_localize_script( 'dp-addthis', 'addthis_share', $addthis_share );
	}

	/**
	 * Enqueue styles.
	 *
	 * @since 1.0.0
	 */
	function enqueue_styles() {
		$css = get_option( 'dp_addthis_custom_css' );

		echo "\n<!-- Custom CSS by (dp) AddThis START -->";
		echo "\n" . '<style type="text/css">' . $css . '</style>';
		echo "\n<!-- Custom CSS by (dp) AddThis END -->";
	}

	function get_config() {
		$config = array_filter( get_option( 'dp_addthis_config', array() ) );
		if ( ! empty( $config['data_ga_property'] ) ) {
			$config['data_ga_social'] = true;
		}

		return apply_filters( 'dp_addthis_config', $config );
	}

	function register_default_settings() {
		add_option( 'dp_addthis_config', array(
			'data_track_clickback' => true
		) );

		add_option( 'dp_addthis_custom_css', '.dp-addthis a {
  text-decoration: none !important;
  box-shadow: none !important;
}

.dp-addthis.addthis_native_toolbox a {
  line-height: 0;
}

.dp-addthis.addthis_native_toolbox iframe {
  margin: 0 !important;
}' );

	}

	/**
	 * Load plugin textdomain
	 *
	 * @since   1.0.0
	 * @return  void
	 */
	function load_textdomain() {
		load_plugin_textdomain( 'dp-addthis', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}
}

$dp_addthis_plugin = new DP_AddThis_Plugin();

/**
 * Inline Tool Filter
 *
 * This class is used to add/remove inline tools to a specific filter.
 *
 * @since 1.0
 */
class DP_AddThis_Inline_Tool_Filter {
	function __construct( $args ) {
		$args = wp_parse_args( $args, array(
			'tool'      => '',
			'location'  => '',
			'placement' => '',
			'filter'    => '',
			'title'     => '',
			'url'       => ''
		) );

		extract( $args );

		if ( $location ) {
			$location_obj = explode( '_', $location );
			if ( ! $placement ) {
				$placement = $location_obj[0];
			}
			if ( ! $filter ) {
				$filter = str_replace( $location_obj[0] . '_', '', $location );
			}
		}

		$this->placement = $placement;
		$this->tool      = $tool;
		$this->filter    = $filter;
		$this->title     = $title;
		$this->url       = $url;
	}

	function add() {
		if ( ! empty( $this->filter ) ) {
			add_filter( $this->filter, array( $this, 'filter' ) );
		}
	}

	function remove() {
		if ( ! empty( $this->filter ) ) {
			remove_filter( $this->filter, array( $this, 'filter' ) );
		}
	}

	function filter( $content ) {

		if ( ! in_the_loop() ) {
			return $content;
		}

		$class = $this->placement == 'before' ? 'before' : 'after';


		$params = array(
			'tool'  => $this->tool,
			'class' => $class
		);
		global $post;
		if ( $post ) {
			$params['url']   = get_permalink( $post );
			$params['title'] = esc_attr( get_the_title( $post ) );
		}

		$addthis = dp_get_addthis( $params );

		if ( $this->placement == 'before' ) {
			$content = $addthis . $content;
		} else {
			$content .= $addthis;
		}

		return $content;
	}
}

// Main Functions
//=======================================================================

/**
 * Display the AddThis tool.
 *
 * @since 1.0.0
 *
 * @param string|array $args
 *
 * @return string
 */
function dp_addthis( $args = '' ) {
	echo dp_get_addthis( $args );
}

/**
 * Retrieve the AddThis tool.
 *
 * @since 1.0.0
 *
 * @param string|array $args {
 * Optional. Array of nav menu arguments.
 *
 * @type string $tool Tool name.
 * @type string $class Extra HTML classnames.
 * }
 * @return string
 */
function dp_get_addthis( $args = '' ) {

	wp_enqueue_script('dp-addthis');

	$defaults = array(
		'tool'  => '',
		'class' => '',
		'title' => '',
		'url'   => ''
	);

	$args = wp_parse_args( $args, $defaults );
	extract( $args );

	$tool_class = 'sharing_toolbox';

	if ( ! empty( $tool ) ) {
		$tools = dp_addthis_get_tools( array( 'is_smartlayer' => false ) );

		if ( array_key_exists( $tool, $tools ) ) {
			$tool_class = $tools[ $tool ]['class'];
		}
	}

	$atts = array(
		'class'      => trim( 'dp-addthis addthis_' . $tool_class . ' ' . $class ),
		'data-title' => $title,
		'data-url'   => $url
	);

	$attr = '';
	foreach ( $atts as $prop => $value ) {
		if ( ! empty( $atts[ $prop ] ) ) {
			$attr .= $prop . '="' . $value . '"';
		}
	}

	$html = '<div ' . $attr . '></div>';

	return apply_filters( 'dp_addthis', $html, $args );
}

/**
 * Builds the AddThis shortcode output.
 *
 * @since 1.0.0
 *
 * @param array $atts
 *
 * @return string HTML content to display AddThis tool.
 */
function dp_addthis_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'tool' => null, 'class' => '' ), $atts, 'addthis' );

	return dp_get_addthis( $atts );
}

add_shortcode( 'addthis', 'dp_addthis_shortcode' );


// API Functions
//=======================================================================

/**
 * Get contexts of the page which is currently viewing on the site.
 *
 * @since  1.0.0
 * @return array
 */
function dp_addthis_get_contexts() {
	$contexts  = array();
	$object    = get_queried_object();
	$object_id = get_queried_object_id();

	// front page
	if ( is_front_page() ) {
		$contexts[] = 'home';
	} // Blog page.
	elseif ( is_home() ) {
		$contexts[] = 'archive-post';
	} // single views
	elseif ( is_singular() ) {
		$contexts[] = 'single';
		$contexts[] = "single-{$object->post_type}";


	} // Archive views
	elseif ( is_archive() ) {
		$contexts[] = 'archive';

		// Post Type archives
		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				reset( $post_type );
			}
			$contexts[] = "archive-{$post_type}";
		}
		// Taxonomy archives
		if ( is_tax() || is_category() || is_tag() ) {
			$contexts[] = 'taxonomy';
			$contexts[] = "taxonomy-{$object->taxonomy}";
		}
		// User/author archives
		if ( is_author() ) {
			$user_id    = get_query_var( 'author' );
			$contexts[] = 'user';
			$contexts[] = 'user-' . sanitize_html_class( get_the_author_meta( 'user_nicename', $user_id ), $user_id );
		}
		// Date archives
		if ( is_date() ) {
			$contexts[] = 'date';
		}
	} // Search results
	elseif ( is_search() ) {
		$contexts[] = 'search';
	}

	return array_map( 'esc_attr', apply_filters( 'dp_addthis_contextss', array_unique( $contexts ) ) );
}

/**
 * Get a list of all context objects.
 *
 * @since  1.0.0
 * @return array
 */
function dp_addthis_get_context_objects( $args = '' ) {
	$contexts = array();

	// home
	$contexts['home'] = array(
		'label' => __( 'Home', 'dp' )
	);

	// single
	$contexts['single'] = array(
		'label' => __( 'Single', 'dp' )
	);

	// single_{$post_type}
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	foreach ( $post_types as $tool => $obj ) {
		$contexts[ 'single-' . $tool ] = array(
			'label' => sprintf( __( 'Single: %s', 'dp' ), $obj->labels->singular_name ),
		);
	}

	// archive
	$contexts['archive'] = array(
		'label' => __( 'Archive', 'dp' )
	);

	// archive_post
	$contexts['archive-post'] = array(
		'label' => __( 'Post Type Archive: Post', 'dp' )
	);

	// archive_{$custom_post_type}
	$post_types = get_post_types( array( 'has_archive' => true ), 'objects' );
	foreach ( $post_types as $type => $obj ) {
		$contexts[ 'archive-' . $type ] = array(
			'label' => sprintf( __( 'Post Type Archive: %s', 'dp' ), $obj->labels->singular_name )
		);
	}

	// taxonomy
	$contexts['taxonomy'] = array(
		'label' => __( 'Taxonomy Archive', 'dp' )
	);
	$taxonomies           = get_taxonomies( array( 'public' => true ), 'objects' );
	foreach ( $taxonomies as $tax => $obj ) {
		$contexts[ 'taxonomy-' . $tax ] = array(
			'label' => sprintf( __( 'Taxonomy Archive: %s', 'dp' ), $obj->labels->singular_name )
		);
	}

	// date
	$contexts['date'] = array( 'label' => __( 'Date Archive', 'dp' ) );

	// author
	$contexts['author'] = array( 'label' => __( 'Author Archive', 'dp' ) );

	// search
	$contexts['search'] = array( 'label' => __( 'Search Results', 'dp' ) );


	return apply_filters( 'dp_addthis_context_objects', $contexts );
}

/**
 * Get a list of all AddThis tools.
 *
 * @since  1.0.0
 * @return array
 */
function dp_addthis_get_tools( $args = array(), $field = false, $operator = 'and' ) {
	$tools = array(
		// Share
		//================================

		'share'                 => array(
			'slug'          => 'tbx',
			'class'         => 'sharing_toolbox',
			'label'         => __( 'Sharing Buttons', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => false,
			'group'         => 'share'
		),
		'nshare'                => array(
			'slug'          => 'scopl',
			'class'         => 'native_toolbox',
			'label'         => __( 'Original Sharing Toolbox', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => false,
			'group'         => 'share'
		),
		'mobile_share_toolbar'  => array(
			'slug'          => 'msd',
			'selector'      => '.at-share-dock-outer',
			'label'         => __( 'Mobile Sharing Toolbar', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => true,
			'group'         => 'share'
		),
		'mobile_toolbar'        => array(
			'slug'          => 'smlmo',
			'selector'      => '#at4m-mobile-container',
			'label'         => __( 'Mobile Toolbar', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => true,
			'group'         => 'share'
		),
		'share_sidebar'         => array(
			'slug'          => 'smlsh',
			'selector'      => '.at4-share-outer',
			'label'         => __( 'Sharing Sidebar', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => true,
			'group'         => 'share'
		),
		'share_sidebar_counter' => array(
			'slug'          => 'smlshp',
			'selector'      => '.at4-share-outer',
			'label'         => __( 'Sharing Sidebar Counters', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => true,
			'group'         => 'share'
		),
		'rshare'                => array(
			'slug'          => 'resh',
			'class'         => 'responsive_sharing',
			'label'         => __( 'Responsive Sharing Buttons', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => false,
			'group'         => 'share'
		),
		'jshare'                => array(
			'slug'          => 'jsc',
			'class'         => 'jumbo_share',
			'label'         => __( 'Jumbo Share Counter', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => true,
			'group'         => 'share'
		),
		'cshare'                => array(
			'slug'          => 'ctbx',
			'class'         => 'custom_sharing',
			'label'         => __( 'Custom Sharing Buttons', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => false,
			'group'         => 'share'
		),

		// Follow
		//================================
		'cfollow'               => array(
			'slug'          => 'cflwh',
			'class'         => 'custom_follow',
			'label'         => __( 'Custom Follow Buttons', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => false,
			'group'         => 'follow'
		),
		'follow_header'         => array(
			'slug'          => 'smlfw',
			'selector'      => '.at4-follow-outer',
			'label'         => __( 'Follow Header', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => true,
			'group'         => 'follow'
		),
		'hfollow'               => array(
			'slug'          => 'flwh',
			'class'         => 'horizontal_follow_toolbox',
			'label'         => __( 'Horizontal Follow Buttons', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => false,
			'group'         => 'follow'
		),
		'vfollow'               => array(
			'slug'          => 'flwv',
			'class'         => 'vertical_follow_toolbox',
			'label'         => __( 'Vertical Follow Buttons', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => false,
			'group'         => 'follow'
		),

		// Recommended Content
		//================================
		'rcd'                   => array(
			'slug'          => 'cod',
			'selector'      => '#at4-drawer-outer-container',
			'label'         => __( 'Recommended Content Drawer', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => true,
			'group'         => 'content'
		),
		'rct'                   => array(
			'slug'          => 'tst',
			'selector'      => '.at4-whatsnext-outer-container',
			'label'         => __( 'Recommended Content Toaster', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => true,
			'group'         => 'content'
		),
		'rcj'                   => array(
			'slug'          => 'jrcf',
			'selector'      => '.at-recommendedjumbo-outer-container',
			'label'         => __( 'Jumbo Recommended Content Footer', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => true,
			'group'         => 'content'
		),
		'rcf'                   => array(
			'slug'          => 'smlre',
			'selector'      => '.at4-recommended-outer-container',
			'label'         => __( 'Recommended Content Footer', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => true,
			'group'         => 'content'
		),
		'hrc'                   => array(
			'slug'          => 'smlrebh',
			'class'         => 'recommended_horizontal',
			'label'         => __( 'Horizontal Recommended Content', 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => false,
			'group'         => 'content'
		),
		'vrc'                   => array(
			'slug'          => 'smlrebv',
			'class'         => 'recommended_vertical',
			'label'         => __( 'Vertical Recommended Content', 'dp-addthis' ),
			'is_pro'        => true,
			'is_smartlayer' => false,
			'group'         => 'content'
		),
		'wn'                    => array(
			'slug'          => 'smlwn',
			'selector'      => '.at4-whatsnext-outer-container',
			'label'         => __( "What's Next", 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => true,
			'group'         => 'content'
		),
		'wnm'                   => array(
			'slug'          => 'wnm',
			'selector'      => '.at4-whatsnext-outer-container',
			'label'         => __( "What's Next Mobile", 'dp-addthis' ),
			'is_pro'        => false,
			'is_smartlayer' => true,
			'group'         => 'content'
		)
	);


	$tools = wp_filter_object_list( $tools, $args, $operator, $field );

	return apply_filters( 'dp_addthis_tools', $tools );
}

/**
 * Retrieve display rules.
 *
 * @since  1.0.0
 * @return array
 */
function dp_addthis_get_rules( $group = '' ) {
	if ( $group == 'inline_tools' || $group == 'smart_layers' ) {
		$rules = (array) get_option( 'dp_addthis_' . $group . '_display_rules', array() );
	} else {
		$inline_rules = (array) get_option( 'dp_addthis_inline_tools_display_rules', array() );
		$smart_rules  = (array) get_option( 'dp_addthis_smart_layers_display_rules', array() );
		$rules        = array_merge( $inline_rules, $smart_rules );
	}

	// exclude pro tools if the user doesn't have an AddThis Pro.
	$is_pro = get_option( 'dp_addthis_is_pro' );
	if ( ! $is_pro ) {
		foreach ( $rules as $key => $rule ) {
			$tool_key = $rule['tool'];
			$tools    = dp_addthis_get_tools();
			if ( ! empty( $tools[ $tool_key ]['is_pro'] ) ) {
				unset( $rules[ $key ] );
			}
		}
	}

	return apply_filters( 'dp_addthis_rules', array_filter( array_values( $rules ) ), $group );
}