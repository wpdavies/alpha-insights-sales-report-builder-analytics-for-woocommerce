<?php
/**
 *
 * Facebook Campaigns Custom Post Type
 *
 * @package Alpha Insights
 * @version 3.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 * @slug cpt facebook_campaign
 * @slug tax ad_account
 * 
 * 		@post_meta
 *		_wpd_campaign_name (string)
 *		_wpd_campaign_id (int)
 *		_wpd_campaign_spend (decimal)
 *		_wpd_campaign_impressions (int)
 *		_wpd_campaign_clicks (int)
 *		_wpd_campaign_outbound_clicks (int)
 *		_wpd_campaign_leads (int)
 *		_wpd_campaign_purchases (int)
 *		_wpd_campaign_purchase_value (decimal)
 *		_wpd_campaign_conversion_rate (decimal)
 *		_wpd_campaign_roas (decimal)
 *		_wpd_totals_data (array)
 *		_wpd_daily_data (array)
 *		_wpd_campaign_start (date Y-m-d)
 *		_wpd_campaign_stop (date Y-m-d)
 *
 */
defined( 'ABSPATH' ) || exit;

// Main Class
class WPD_Facebook_Campaigns_CPT {

	public function __construct() {


		// Initialise custom post type
		add_action( 'init', array( $this, 'register_facebook_campaign_cpt' ), 10 );

		// Initialise custom post type taxonomy
		add_action( 'init', array( $this, 'facebook_taxonomies' ), 0 );

		// Meta boxes
		add_action( 'admin_init', array( $this, 'register_facebook_campaign_data_meta_boxes' ) );

		// Save info
		// add_action( 'save_post', array( $this, 'save_facebook_campaign_data') );

		// Modify Facebook Campaign post columns
		add_filter( 'manage_facebook_campaign_posts_columns', array($this, 'modify_facebook_campaign_columns') );
		add_action( 'manage_facebook_campaign_posts_custom_column' , array($this, 'facebook_campaign_column_data'), 10, 2 );

		// Sort columns
		add_action( 'pre_get_posts', array( $this, 'sort_facebook_campaign_columns' ) );
		add_filter( 'manage_edit-facebook_campaign_sortable_columns', array($this, 'set_facebook_campaign_sortable_columns' ) );

		// Add new filters to custom tax
		add_action('restrict_manage_posts', array($this,'filter_post_type_by_taxonomy')); // Show filter
		add_filter('parse_query', array($this,'convert_id_to_term_in_query')); // Parse the query

	}

	/** 
	 *
	 *	Register Data Inputs - Post Page
	 *
	 */
	public function register_facebook_campaign_data_meta_boxes() {

		// add_meta_box( $id, $title, $callback, $page, $context, $priority );
		add_meta_box( "facebook_meta", "Campaign Data", array( $this, "facebook_campaign_meta" ), "facebook_campaign", "normal", "low" ); // <- Main info

	}

	/** 
	 *
	 *	Campaign Insight Post Data / Page Layout
	 *	@todo THIS
	 *
	 */
	public function facebook_campaign_meta() {

		global $post;
		$post_id = $post->ID;

		$campaign_name 			= get_post_meta( $post_id, "_wpd_campaign_name", true );
		$campaign_id 			= get_post_meta( $post_id, "_wpd_campaign_id", true );
		$campaign_data 			= get_post_meta( $post_id, "_wpd_totals_data", true );
		$daily_data 			= get_post_meta( $post_id, "_wpd_daily_data", true );
		$campaign_start 		= get_post_meta( $post_id, "_wpd_campaign_start", true );
		$campaign_stop 			= get_post_meta( $post_id, "_wpd_campaign_stop", true );
		$campaign_roas 			= get_post_meta( $post_id, "_wpd_campaign_roas", true );
		$campaign_revenue 		= get_post_meta( $post_id, "_wpd_campaign_purchase_value", true );
		$campaign_leads 		= get_post_meta( $post_id, "_wpd_campaign_leads", true );
		$campaign_purchases 	= get_post_meta( $post_id, "_wpd_campaign_purchases", true );
		$ad_account 			= $campaign_data['account_name'];
		$campaign_spend 		= $campaign_data['spend'] . ' ' . $campaign_data['account_currency'];
		$campaign_impressions 	= $campaign_data['impressions'];
		$campaign_clicks 		= $campaign_data['clicks'];

		wpd_debug( $daily_data, 'Daily Data' );
		wpd_debug( $campaign_data, 'Totals Data' );


		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th>Account Name</th><td><?php echo $ad_account ?></td>
				</tr>
				<tr>
					<th>Campaign ID</th><td><?php echo $campaign_id ?></td>
				</tr>
				<tr>
					<th>Campaign Spend</th><td><?php echo $campaign_spend ?></td>
				</tr>
				<tr>
					<th>Campaign Impressions</th><td><?php echo $campaign_impressions ?></td>
				</tr>
				<tr>
					<th>Campaign Clicks</th><td><?php echo $campaign_clicks ?></td>
				</tr>
				<tr>
					<th>Outbound Clicks</th><td><?php echo $campaign_data['outbound_clicks']; ?></td>
				</tr>
				<tr>
					<th>Campaign Start</th><td><?php echo $campaign_start ?></td>
				</tr>
				<tr>
					<th>Campaign Stop</th><td><?php echo $campaign_stop ?></td>
				</tr>
				<tr>
					<th>Days Active</th><td><?php echo $campaign_data['days_active']; ?></td>
				</tr>
				<tr>
					<th>Campaign ROAS</th><td><?php echo $campaign_roas ?></td>
				</tr>
				</tr>
				<tr>
					<th>Campaign Revenue</th><td><?php echo $campaign_revenue ?></td>
				</tr>
				<tr>
					<th>Campaign Purchases</th><td><?php echo $campaign_purchases ?></td>
				</tr>
				<tr>
					<th>Campaign Leads</th><td><?php echo $campaign_leads ?></td>
				</tr>
				<tr>
					<th>Last Updated</th><td><?php echo ($campaign_data['last_updated_unix'] ) ? date('F j, Y h:i:s', $campaign_data['last_updated_unix']) : 'N/A'; ?></td>
				</tr>
			</tbody>
		</table>
		<?php

	}

	/** 
	 *
	 *	Process data on save
	 *	@todo this
	 *	
	 */
	public function save_facebook_campaign_data() {

		global $post;

/*		if ( isset($_POST["_wpd_amount_paid"]) ) {
			$amount_paid = sanitize_text_field( $_POST["_wpd_amount_paid"] );
			update_post_meta( $post->ID, "_wpd_amount_paid", $amount_paid );
		}*/
	}

	/**
	 * Registers post types needed by the plugin.
	 *
	 * @since  0.1.0
	 * @access public
	 * @return void
	 */
	public function register_facebook_campaign_cpt() {

		// flush_rewrite_rules( );


		/* Set up the arguments for the post type. */
		$args = array(

			/*
			 * A short description of what your post type is. As far as I know, this isn't used anywhere 
			 * in core WordPress.  However, themes may choose to display this on post type archives. 
			 */
			'description'         => __( 'Facebook Campaign Insight Data For Alpha Insights.', 'wpd-alpha-insights' ), // string

			/**
			 * Whether the post type should be used publicly via the admin or by front-end users.  This 
			 * argument is sort of a catchall for many of the following arguments.  I would focus more 
			 * on adjusting them to your liking than this argument.
			 *	@since  
			 */
			'public'              => false, // bool (default is FALSE)

			/*
			 * Whether queries can be performed on the front end as part of parse_request(). 
			 */
			'publicly_queryable'  => false, // bool (defaults to 'public'). <- stops it showing on frontend

			/*
			 * Whether to exclude posts with this post type from front end search results.
			 */
			'exclude_from_search' => true, // bool (defaults to FALSE - the default of 'internal')

			/*
			 * Whether individual post type items are available for selection in navigation menus. 
			 */
			'show_in_nav_menus'   => false, // bool (defaults to 'public')

			/*
			 * Whether to generate a default UI for managing this post type in the admin. You'll have 
			 * more control over what's shown in the admin with the other arguments.  To build your 
			 * own UI, set this to FALSE.
			 */
			'show_ui'             => true, // bool (defaults to 'public')

			/*
			 * Whether to show post type in the admin menu. 'show_ui' must be true for this to work. 
			 */
			'show_in_menu'        => false, // bool (defaults to 'show_ui')

			/*
			 * Whether to make this post type available in the WordPress admin bar. The admin bar adds 
			 * a link to add a new post type item.
			 */
			'show_in_admin_bar'   => true, // bool (defaults to 'show_in_menu')

			/*
			 * The position in the menu order the post type should appear. 'show_in_menu' must be true 
			 * for this to work.
			 */
			'menu_position'       => 6, // int (defaults to 25 - below comments)

			/*
			 * The URI to the icon to use for the admin menu item. There is no header icon argument, so 
			 * you'll need to use CSS to add one.
			 */
			'menu_icon'           => WPD_AI_URL_PATH . 'assets/img/Alpha-Insights-Icon-20x20.png', // string (defaults to use the post icon)

			/*
			 * Whether the posts of this post type can be exported via the WordPress import/export plugin 
			 * or a similar plugin. 
			 */
			'can_export'          => true, // bool (defaults to TRUE)

			/*
			 * Whether to delete posts of this type when deleting a user who has written posts. 
			 */
			'delete_with_user'    => false, // bool (defaults to TRUE if the post type supports 'author')

			/*
			 * Whether this post type should allow hierarchical (parent/child/grandchild/etc.) posts. 
			 */
			'hierarchical'        => true, // bool (defaults to FALSE)

			/* 
			 * Whether the post type has an index/archive/root page like the "page for posts" for regular 
			 * posts. If set to TRUE, the post type name will be used for the archive slug.  You can also 
			 * set this to a string to control the exact name of the archive slug.
			 */
			'has_archive'         => false, // bool|string (defaults to FALSE)

			/*
			 * Sets the query_var key for this post type. If set to TRUE, the post type name will be used. 
			 * You can also set this to a custom string to control the exact key.
			 */
			'query_var'           => true, // bool|string (defaults to TRUE - post type name)

			/*
			 * (array) (optional) An array of registered taxonomies like category or post_tag that will be used with this post type.
			 * This can be used in lieu of calling register_taxonomy_for_object_type() directly. Custom taxonomies still need to be registered with register_taxonomy() .
			 */
			'taxonomies'           => array('ad_account'), // (array) (optional) - by slug

			/* 
			 * How the URL structure should be handled with this post type.  You can set this to an 
			 * array of specific arguments or true|false.  If set to FALSE, it will prevent rewrite 
			 * rules from being created.
			 */
			'rewrite' => array(

				/* The slug to use for individual posts of this type. */
				'slug'       => 'facebook-campaign', // string (defaults to the post type name)

				/* Whether to show the $wp_rewrite->front slug in the permalink. */
				'with_front' => false, // bool (defaults to TRUE)

				/* Whether to allow single post pagination via the <!--nextpage--> quicktag. */
				'pages'      => true, // bool (defaults to TRUE)

				/* Whether to create pretty permalinks for feeds. */
				'feeds'      => true, // bool (defaults to the 'has_archive' argument)

				/* Assign an endpoint mask to this permalink. */
				'ep_mask'    => EP_PERMALINK, // const (defaults to EP_PERMALINK)
			),

			'supports' => array(

				'title',

			),

			'labels' => array(

				'name'               => __( 'Facebook Campaigns',          'wpd-alpha-insights' ),
				'singular_name'      => __( 'Facebook Campaign',           'wpd-alpha-insights' ),
				'menu_name'          => __( 'Facebook Campaigns',          'wpd-alpha-insights' ),
				'name_admin_bar'     => __( 'Facebook Campaigns',          'wpd-alpha-insights' ),
				'add_new'            => __( 'Add New',                     'wpd-alpha-insights' ),
				'add_new_item'       => __( 'Add New Campaign',            'wpd-alpha-insights' ),
				'edit_item'          => __( 'Edit Campaign',               'wpd-alpha-insights' ),
				'new_item'           => __( 'New Facebook Campaign',       'wpd-alpha-insights' ),
				'view_item'          => __( 'View Campaign',               'wpd-alpha-insights' ),
				'search_items'       => __( 'Search Facebook Campaigns',   'wpd-alpha-insights' ),
				'not_found'          => __( 'No Campaigns found',          'wpd-alpha-insights' ),
				'not_found_in_trash' => __( 'No Campaigns found in trash', 'wpd-alpha-insights' ),
				'all_items'          => __( 'All Facebook Campaigns',      'wpd-alpha-insights' ),
				'parent_item'        => __( 'Parent Campaign',             'wpd-alpha-insights' ),
				'parent_item_colon'  => __( 'Parent Campaign:',            'wpd-alpha-insights' ),
				'archive_title'      => __( 'Facebook Campaigns',          'wpd-alpha-insights' ),

			)

		);

		/* Register the post type. */
		register_post_type (

			'facebook_campaign', // Post type name. Max of 20 characters. Uppercase and spaces not allowed.
			$args      // Arguments for post type.

		);

	}

	/**
	 *
	 *	Taxonomies for Facebook Campaigns - Ad Account
	 *
	 */
	public function facebook_taxonomies() {

	  $labels = array(

	    'name'              => _x( 'Ad Account', 'taxonomy general name' ),
	    'singular_name'     => _x( 'Ad Account', 'taxonomy singular name' ),
	    'search_items'      => __( 'Search Ad Accounts', 'wpd-alpha-insights' ),
	    'all_items'         => __( 'All Ad Accounts', 'wpd-alpha-insights' ),
	    'parent_item'       => __( 'Parent Ad Accounts', 'wpd-alpha-insights' ),
	    'parent_item_colon' => __( 'Parent Ad Account:', 'wpd-alpha-insights' ),
	    'edit_item'         => __( 'Edit Ad Account', 'wpd-alpha-insights' ), 
	    'update_item'       => __( 'Update Ad Account', 'wpd-alpha-insights' ),
	    'add_new_item'      => __( 'Add New Ad Account', 'wpd-alpha-insights' ),
	    'new_item_name'     => __( 'New Ad Account', 'wpd-alpha-insights' ),
	    'menu_name'         => __( 'Ad Accounts', 'wpd-alpha-insights' ),
	    'not_found' 		=> __( 'No Ad Accounts Found', 'wpd-alpha-insights' ),

	  );

	  $args = array(

	    'labels' 				=> $labels,
	    'public' 				=> true,
	    'publicly_queryable' 	=> false,
	    'show_ui' 				=> true,
	    'show_in_menu' 			=> false,
	    'show_admin_column' 	=> true,
	    'hierarchical' 			=> true,

	  );

	  register_taxonomy( 'ad_account', 'facebook_campaign', $args );

	}

	/**
	 *
	 *	Set sort order
	 *
	 */
	function sort_facebook_campaign_columns( $query ) {

		if ( $query->is_main_query() ) {

			// wpd_debug( $query );

		}

		// Only apply these settings to this page
		$post_type = ( isset($query->query['post_type']) ) ? $query->query['post_type'] : '';

		if ( is_admin() && $query->is_main_query() && $post_type === 'facebook_campaign' ) {
	
			if ( 'spend' === $query->get( 'orderby') ) {

				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'meta_key', '_wpd_campaign_spend' );
				$query->set( 'meta_type', 'DECIMAL' );

			} elseif ( 'clicks' === $query->get( 'orderby') ) {

				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'meta_key', '_wpd_campaign_outbound_clicks' );
				$query->set( 'meta_type', 'NUMERIC' );

			} elseif ( 'leads' === $query->get( 'orderby') ) {

				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'meta_key', '_wpd_campaign_leads' );
				$query->set( 'meta_type', 'NUMERIC' );

			} elseif ( 'orders' === $query->get( 'orderby') ) {

				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'meta_key', '_wpd_campaign_purchases' );
				$query->set( 'meta_type', 'NUMERIC' );

			} elseif ( 'purchase_value' === $query->get( 'orderby') ) {

				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'meta_key', '_wpd_campaign_purchase_value' );
				$query->set( 'meta_type', 'DECIMAL' );

			} elseif ( 'roas' === $query->get( 'orderby') ) {

				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'meta_key', '_wpd_campaign_roas' );
				$query->set( 'meta_type', 'DECIMAL' );

			} elseif ( 'conversion_rate' === $query->get( 'orderby') ) {

				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'meta_key', '_wpd_campaign_conversion_rate' );
				$query->set( 'meta_type', 'DECIMAL' );

			} else {

				$query->set( 'orderby', 'publish_date' );
				$query->set( 'order', 'DESC' );

			}
			
		} else {

			// This is for other posts, do nothing.

		}

		return $query;


	}

	/** 
	 *
	 *	Set sortable columns
	 *
	 */
	function set_facebook_campaign_sortable_columns( $columns ) {

		$columns['campaign_start'] 	= 'campaign_start';
		$columns['spend'] 			= 'spend';
		$columns['clicks'] 			= 'clicks';
		$columns['leads'] 			= 'leads';
		$columns['orders'] 			= 'orders';
		$columns['conversion_rate'] = 'conversion_rate';
		$columns['purchase_value'] 	= 'purchase_value';
		$columns['roas'] 			= 'roas';

		return $columns;

	}

	/**
	 *
	 *	Add the custom columns to the book post type:
	 *
	 */
	public function modify_facebook_campaign_columns($columns) {

	    $columns['title'] 				= __( 'Campaign', 'wpd-alpha-insights' );
	    $columns['campaign_start'] 		= __( 'Start Date', 'wpd-alpha-insights' );
	    $columns['spend'] 				= __( 'Ad Spend', 'wpd-alpha-insights' );
	    $columns['clicks'] 				= __( 'Website Views', 'wpd-alpha-insights' );
	    $columns['leads'] 				= __( 'Leads', 'wpd-alpha-insights' );
	    $columns['orders'] 				= __( 'Orders', 'wpd-alpha-insights' );
	    $columns['conversion_rate'] 	= __( 'Conversion Rate', 'wpd-alpha-insights' );
	    $columns['purchase_value'] 		= __( 'Revenue', 'wpd-alpha-insights' );
	    $columns['roas'] 				= __( 'ROAS', 'wpd-alpha-insights' );

	    if ( isset($columns['author']) ) unset($columns['author']);
	    if ( isset($columns['date']) ) unset($columns['date']);

	    return $columns;

	}

	/**
	 *
	 *	Add the data to the custom columns for the book post type:
	 *
	 */
	public function facebook_campaign_column_data( $column, $post_id ) {

	    $totals = get_post_meta( $post_id, '_wpd_totals_data', true );

	    switch ( $column ) {

	        case 'campaign_start' :

	        	$start_date = get_post_meta( $post_id, '_wpd_campaign_start', true );
	        	echo $start_date;
	            break;

	        case 'spend' :

	        	$spend = get_post_meta( $post_id, '_wpd_campaign_spend', true );
	        	echo $spend . ' ' . $totals['account_currency'];
	            break;

	        case 'clicks' :

	        	$clicks = get_post_meta( $post_id, '_wpd_campaign_outbound_clicks', true );
	            echo $clicks; 
	            break;

	        case 'leads' :

	        	$leads = get_post_meta( $post_id, '_wpd_campaign_leads', true );
	        	echo $leads;
	            break;

	        case 'orders' :

	        	$orders = get_post_meta( $post_id, '_wpd_campaign_purchases', true );
	        	echo $orders;
	            break;

	        case 'purchase_value' :

	        	$purchase_value = get_post_meta( $post_id, '_wpd_campaign_purchase_value', true );
	            echo $purchase_value . ' ' . $totals['account_currency'];
	            break;


	        case 'roas' :

	        	$roas = get_post_meta( $post_id, '_wpd_campaign_roas', true );
	            echo $roas; 
	            break;

	        case 'conversion_rate' :

	        	$conversion_rate = get_post_meta( $post_id, '_wpd_campaign_conversion_rate', true );
	            echo $conversion_rate . '%'; 
	            break;

	    }

	}

	/**
	 * Display a custom taxonomy dropdown in admin
	 */
	function filter_post_type_by_taxonomy() {

		global $typenow;

		$post_type = 'facebook_campaign'; // change to your post type
		$taxonomy  = 'ad_account'; // change to your taxonomy

		if ($typenow == $post_type) {

			$selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
			$info_taxonomy = get_taxonomy($taxonomy);

			wp_dropdown_categories(array(
				'show_option_all' => sprintf( __( 'Show all %s', 'wpd-alpha-insights' ), $info_taxonomy->label ),
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'selected'        => $selected,
				'show_count'      => true,
				'hide_empty'      => true,
				'hierarchical' 	  => true,
			));

		};

	}

	/**
	 * Filter posts by taxonomy in admin
	 */
	function convert_id_to_term_in_query($query) {

		global $pagenow;

		$post_type = 'facebook_campaign'; // change to your post type
		$taxonomy  = 'ad_account'; // change to your taxonomy
		$q_vars    = &$query->query_vars;

		if ( $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $post_type && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {

			$term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
			$q_vars[$taxonomy] = $term->slug;

		}

	}

}

/**
 *	Initialize
 */
new WPD_Facebook_Campaigns_CPT();