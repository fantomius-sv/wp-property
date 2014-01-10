<?php
/**
 * UsabilityDynamics\WPP Bootstrap
 *
 * Migrated
 * ========
 *
 * - WPP_F::load_modules
 * - WPP_F::check_premium
 * - WPP_F::check_plugin_updates
 * - WPP_F::manual_activation
 * - upgrade
 *
 * @verison 2.0.0
 * @author potanin@UD
 * @namespace UsabilityDynamics\WPP
 */
namespace UsabilityDynamics\WPP {

  if( !class_exists( 'UsabilityDynamics\WPP\Bootstrap' ) ) {

    /**
     * WP-Property Bootstrap
     *
     * Contains primary functions for setting up the framework of the plugin.
     *
     * @class Bootstrap
     * @version 2.0.0
     * @author Usability Dynamics, Inc. <info@usabilitydynamics.com>
     * @package WP-Property
     * @subpackage Bootstrap
     * @namespace UsabilityDynamics\WPP
     */
    class Bootstrap {

      /**
       * Plugin Version.
       *
       * @static
       * @property $version
       * @type String
       */
      public static $version = '2.0.0';

      /**
       * Name of Primary Object.
       *
       * @static
       * @property $object
       * @type String
       */
      public static $object = 'property';

      /**
       * Textdomain String
       *
       * @public
       * @property text_domain
       * @var string
       */
      public static $text_domain = 'wpp';

      /**
       * Singleton Instance Reference.
       *
       * @public
       * @static
       * @property $instance
       * @type {Object}
       */
      public static $instance = false;

      /**
       * Settings Instance.
       *
       * @static
       * @property $_settings
       * @type {Object}
       */
      public $_settings = false;

      /**
       * UI Instance.
       *
       * @static
       * @property $_ui
       * @type {Object}
       */
      public $_ui = false;

      /**
       * API Instance.
       *
       * @static
       * @property $_api
       * @type {Object}
       */
      public $_api = false;

      /**
       * Current Theme.
       *
       * @static
       * @property $_theme
       * @type {Object}
       */
      public $_theme = false;

      /**
       * File Path
       *
       * @static
       * @property $_path
       * @type {String}
       */
      public $_path = null;

      /**
       * Vendor Path.
       *
       * @static
       * @property $_vendor
       * @type {String}
       */
      public $_vendor = null;

      /**
       * URL to Base.
       *
       * @static
       * @property $_url
       * @type {Object}
       */
      public $_url = null;

      /**
       * Constructor.
       *
       * UsabilityDynamics components should be avialable.
       * - class_exists( '\UsabilityDynamics\API' );
       * - class_exists( '\UsabilityDynamics\Utility' );
       *
       * @for Loader
       * @method __construct
       */
      public function __construct() {
        global $wpdb, $wpp, $wp_properties;

        // Return Singleton Instance.
        if( self::$instance ) {
          return self::$instance;
        }

        // Save Instance.
        self::$instance = $wpp = &$this;

        // Define Constants.
        $this->define_constants();

        // Autoload Vendor Dependencies.
        $this->autoload();

        // property_meta
        // location_matters
        // searchable_property_types
        // searchable_attributes
        // search_conversions
        // image_sizes
        // configuration.google_maps.infobox_attributes
        // hidden_attributes
        // property_stats
        // property_types
        // property_inheritance

        $this->_utility   = new Utility;

        // Instantiate and load settings.
        $this->_settings  = new Settings(array(
          "store" => "options",
          "key" => "wpp_settings",
          "schema" => WPP_Path . 'static/schemas/system.settings.schema.json'
        ));

        // Set vendor path in settings for easier access.
        $this->set( 'paths.vendor', $this->_vendor . '/usabilitydynamics' );

        // @note Hopefully temporary but this exposes settings to the legacy $wp_properties global variable.
        $wp_properties = $this->get();

        // Load Default API and Template Functions.
        Template::load( 'default-api.php' );
        Template::load( 'template-functions.php' );

        // Register activation hook -> has to be in the main plugin file
        register_activation_hook( __FILE__, array( &$this, 'activation' ) );

        // Register activation hook -> has to be in the main plugin file
        register_deactivation_hook( __FILE__, array( &$this, 'deactivation' ) );

        // Initiate the plugin
        add_action( 'after_setup_theme', array( &$this, 'setup_theme' ), 10 );

        // Hook in upper init
        add_action( 'init', array( &$this, 'init_upper' ), 0 );

        // Hook in lower init
        add_action( 'init', array( &$this, 'init_lower' ), 100 );

        // Setup Template Redirection.
        add_action( "template_redirect", array( &$this, 'template_redirect' ) );

        // Check settings data on accord with existing wp_properties data before option updates
        add_filter( 'wpp_settings_save', array( &$this, 'check_wp_settings_data' ), 0, 2 );

        // Modify request to change feed
        add_filter( 'request', array( &$this, 'property_feed' ) );

        // Load Modules.
        $this->load_modules();

        // Initialize Shortcodes.
        $this->load_shortcodes();

        // Find and Initialize Widgets.
        $this->load_widgets();

        // Settings Action.
        $this->settings_action();

        // Register JavaScript Libraries.
        $this->register_libraries();

        // Register CSS Assets.
        $this->register_styles();

      }

      /**
       * Load settings into $wp_properties and save settings if nonce exists
       *
       */
      private function settings_action() {
        return Utility::settings_action();
      }

      /**
       * Load and Instantiate Available Modules
       *
       * @todo Add some logic to prevent broken libraries form being re-downloaded on every request by being disabled in settings on failure.
       */
      private function load_modules() {

        // @debug Declaring "active" modules for current client.
        $this->set( 'modules.active', array(
          //'wp-property-test-module',
          'wp-property-admin-tools',
          'wp-property-power-tools'
        ));

        // Load required modules into UD Vendor directory.
        $_required = Module::load( array(
          'required' => $this->get( 'modules.active' ),
          'path' => $this->get( 'paths.vendor' )
        ));

      }

      /**
       * Autoload Vendor Dependencies
       *
       * @author potanin@UD
       * @since 2.0.0
       */
      private function autoload() {

        // Seek ./vendor/autoload.php and autoload
        if( !is_file( $this->_vendor . '/autoload.php' ) ) {
          self::fail( 'WP-Property vendor directory missing; attempted to find it in: ' . $this->_vendor . '/autoload.php' );
        }

        // Vendor Autoloader.
        include_once( $this->_vendor . '/autoload.php' );

        // Legacy Support.
        include_once( $this->_path . 'lib/legacy.php' );

      }

      /**
       * Define Plugin Constants
       *
       *
       * @author potanin@UD
       * @since 2.0.0
       */
      private function define_constants() {

        $this->_path = trailingslashit( dirname( plugin_dir_path( __FILE__ ) ) );
        $this->_vendor = trailingslashit( dirname( plugin_dir_path( __FILE__ ) ) ) . 'vendor';
        $this->_url = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) );

        define( 'WPP_Version', self::$version );
        define( 'WPP_Object', self::$object );
        define( 'WPP_Path', $this->_path );
        define( 'WPP_URL', $this->_url );
        define( 'WPP_Templates', WPP_Path . 'templates' );
        define( 'WPP_Premium', WPP_Path . $this->_vendor . '/usabilitydynamics' );
      }

      /**
       * Register Taxonomies.
       *
       */
      private function register_taxonomies() {

        // Setup Taxonomies.
        $_taxonomies = array(
          'property_feature'  => array(
            'hierarchical' => false,
            'label'        => _x( 'Features', 'taxonomy general name', 'wpp' ),
            'labels'       => array(
              'name'              => _x( 'Features', 'taxonomy general name', 'wpp' ),
              'singular_name'     => _x( 'Feature', 'taxonomy singular name', 'wpp' ),
              'search_items'      => __( 'Search Features', 'wpp' ),
              'all_items'         => __( 'All Features', 'wpp' ),
              'parent_item'       => __( 'Parent Feature', 'wpp' ),
              'parent_item_colon' => __( 'Parent Feature:', 'wpp' ),
              'edit_item'         => __( 'Edit Feature', 'wpp' ),
              'update_item'       => __( 'Update Feature', 'wpp' ),
              'add_new_item'      => __( 'Add New Feature', 'wpp' ),
              'new_item_name'     => __( 'New Feature Name', 'wpp' ),
              'menu_name'         => __( 'Feature', 'wpp' )
            ),
            'query_var'    => 'property_feature',
            'rewrite'      => array( 'slug' => 'feature' )
          ),
          'community_feature' => array(
            'hierarchical' => false,
            'label'        => _x( 'Community Features', 'taxonomy general name', 'wpp' ),
            'labels'       => array(
              'name'              => _x( 'Community Features', 'taxonomy general name', 'wpp' ),
              'singular_name'     => _x( 'Community Feature', 'taxonomy singular name', 'wpp' ),
              'search_items'      => __( 'Search Community Features', 'wpp' ),
              'all_items'         => __( 'All Community Features', 'wpp' ),
              'parent_item'       => __( 'Parent Community Feature', 'wpp' ),
              'parent_item_colon' => __( 'Parent Community Feature:', 'wpp' ),
              'edit_item'         => __( 'Edit Community Feature', 'wpp' ),
              'update_item'       => __( 'Update Community Feature', 'wpp' ),
              'add_new_item'      => __( 'Add New Community Feature', 'wpp' ),
              'new_item_name'     => __( 'New Community Feature Name', 'wpp' ),
              'menu_name'         => __( 'Community Feature', 'wpp' )
            ),
            'query_var'    => 'community_feature',
            'rewrite'      => array( 'slug' => 'community_feature' )
          )
        );

        $wp_properties[ 'taxonomies' ] = apply_filters( 'wpp_taxonomies', $_taxonomies );

        foreach( (array) $wp_properties[ 'taxonomies' ] as $taxonomy => $taxonomy_data ) {

          //** Check if taxonomy is disabled */
          if( isset( $wp_properties[ 'configuration' ][ 'disabled_taxonomies' ] ) &&
            is_array( $wp_properties[ 'configuration' ][ 'disabled_taxonomies' ] ) &&
            in_array( $taxonomy, $wp_properties[ 'configuration' ][ 'disabled_taxonomies' ] )
          ) {
            continue;
          }

          register_taxonomy( $taxonomy, 'property', array(
            'hierarchical' => $taxonomy_data[ 'hierarchical' ],
            'label'        => $taxonomy_data[ 'label' ],
            'labels'       => $taxonomy_data[ 'labels' ],
            'query_var'    => $taxonomy,
            'rewrite'      => array( 'slug' => $taxonomy ),
            'capabilities' => array(
              'manage_terms' => 'manage_wpp_categories',
              'edit_terms'   => 'manage_wpp_categories',
              'delete_terms' => 'manage_wpp_categories',
              'assign_terms' => 'manage_wpp_categories'
            )
          ));

        }

      }

      /**
       * Registers post types and taxonomies.
       *
       * @since 1.31.0
       */
      private function register_post_type() {
        global $wp_properties;

        $wp_properties[ 'labels' ] = apply_filters( 'wpp_object_labels', array(
          'name'               => __( 'Properties', 'wpp' ),
          'all_items'          => __( 'All Properties', 'wpp' ),
          'singular_name'      => __( 'Property', 'wpp' ),
          'add_new'            => __( 'Add Property', 'wpp' ),
          'add_new_item'       => __( 'Add New Property', 'wpp' ),
          'edit_item'          => __( 'Edit Property', 'wpp' ),
          'new_item'           => __( 'New Property', 'wpp' ),
          'view_item'          => __( 'View Property', 'wpp' ),
          'search_items'       => __( 'Search Properties', 'wpp' ),
          'not_found'          => __( 'No properties found', 'wpp' ),
          'not_found_in_trash' => __( 'No properties found in Trash', 'wpp' ),
          'parent_item_colon'  => ''
        ) );

        // Register custom post types
        register_post_type( 'property', array(
          'labels'              => $wp_properties[ 'labels' ],
          'public'              => true,
          'exclude_from_search' => $wp_properties[ 'configuration' ][ 'include_in_regular_search_results' ] == 'true' ? false : true,
          'show_ui'             => true,
          '_edit_link'          => 'post.php?post=%d',
          'capability_type'     => array( 'wpp_property', 'wpp_properties' ),
          'hierarchical'        => true,
          'rewrite'             => array(
            'slug' => $wp_properties[ 'configuration' ][ 'base_slug' ]
          ),
          'query_var'           => $wp_properties[ 'configuration' ][ 'base_slug' ],
          'supports'            => array( 'title', 'editor', 'thumbnail', 'comments' )
          //'menu_icon'           => WPP_URL . 'images/pp_menu-1.6.png'
        ));

      }

      /**
       * Regiser CSS Assets.
       *
       * @author potanin@UD
       */
      private function register_styles() {

        /** Find and register stylesheet  */
        if( file_exists( STYLESHEETPATH . '/wp-properties.css' ) ) {
          wp_register_style( 'wp-property-frontend', get_bloginfo( 'stylesheet_directory' ) . '/wp-properties.css', array(), WPP_Version );
        } elseif( file_exists( STYLESHEETPATH . '/wp_properties.css' ) ) {
          wp_register_style( 'wp-property-frontend', get_bloginfo( 'stylesheet_directory' ) . '/wp_properties.css', array(), WPP_Version );
        } elseif( file_exists( TEMPLATEPATH . '/wp-properties.css' ) ) {
          wp_register_style( 'wp-property-frontend', get_bloginfo( 'template_url' ) . '/wp-properties.css', array(), WPP_Version );
        } elseif( file_exists( TEMPLATEPATH . '/wp_properties.css' ) ) {
          wp_register_style( 'wp-property-frontend', get_bloginfo( 'template_url' ) . '/wp_properties.css', array(), WPP_Version );
        } elseif( file_exists( WPP_Templates . '/wp_properties.css' ) && $wp_properties[ 'configuration' ][ 'autoload_css' ] == 'true' ) {
          wp_register_style( 'wp-property-frontend', WPP_URL . 'templates/wp_properties.css', array(), WPP_Version );

          //** Find and register theme-specific style if a custom wp_properties.css does not exist in theme */
          if( $wp_properties[ 'configuration' ][ 'do_not_load_theme_specific_css' ] != 'true' && Utility::has_theme_specific_stylesheet() ) {
            wp_register_style( 'wp-property-theme-specific', WPP_URL . "templates/theme-specific/" . get_option( 'template' ) . ".css", array( 'wp-property-frontend' ), WPP_Version );
          }
        }

        wp_register_style( 'wpp-jquery-fancybox-css', WPP_URL . 'third-party/fancybox/jquery.fancybox-1.3.4.css' );
        wp_register_style( 'wpp-jquery-colorpicker-css', WPP_URL . 'vendor/usabilitydynamics/lib-js-colorpicker/styles/colorpicker.css' );
        wp_register_style( 'jquery-ui', WPP_URL . 'styles/jquery-ui.css' );
        wp_register_style( 'wpp-jquery-data-tables', WPP_URL . "styles/wpp-data-tables.css" );

      }

      /**
       * Register JavaScript Libraries
       *
       * @author potanin@UD
       */
      private function register_libraries() {

        // Instantiate UDX Library Manager.
        new \UsabilityDynamics\Requires(array(
          'paths' => '/scripts/app.state.js',
          'scopes' => array( 'admin' ),
          'debug' => true
        ));

        // Register UDX Libraries.
        wp_register_script( 'udx.requires',       '//cdn.udx.io' );
        //wp_register_script( 'udx.knockout',       '//cdn.udx.io/knockout.js' );
        //wp_register_script( 'udx.utility.cookie', '//cdn.udx.io/utility.cookie.js' );
        //wp_register_script( 'udx.utility.md5',    '//cdn.udx.io/utility.md5.js' );

        // Register WP-Property Global Libraries.
        wp_register_script( 'wpp.global', WPP_URL . 'scripts/wpp.global.js', array( 'jquery', 'wpp.localization', 'udx.requires' ), WPP_Version );
        wp_register_script( 'wpp.localization', get_bloginfo( 'wpurl' ) . '/wp-admin/admin-ajax.php?action=wpp_js_localization', array(), WPP_Version );

        // Register WP-Property Admin Libraries.
        wp_register_script( 'wpp.admin', WPP_URL . 'scripts/wpp.admin.global.js', array( 'jquery', 'wpp.global', 'wpp.localization' ), WPP_Version );
        wp_register_script( 'wpp.admin.modules', WPP_URL . 'scripts/wpp.admin.modules.js', array( 'wpp.localization', 'udx.requires' ), WPP_Version );
        wp_register_script( 'wpp.admin.settings', WPP_URL . 'scripts/wpp.admin.settings.js', array( 'wpp.localization', 'udx.requires' ), WPP_Version );
        wp_register_script( 'wpp.admin.overview', WPP_URL . 'scripts/wpp.admin.overview.js', array( 'jquery', 'wpp.localization' ), WPP_Version );
        wp_register_script( 'wpp.admin.widgets', WPP_URL . 'scripts/wpp.admin.widgets.js', array( 'jquery', 'wpp.localization' ), WPP_Version );

        // Register Vendor Libraries.
        wp_register_script( 'wp-property-galleria', WPP_URL . 'third-party/galleria/galleria-1.2.5.js', array( 'jquery', 'wpp.localization' ) );
        wp_register_script( 'wpp-jquery-fancybox', WPP_URL . 'third-party/fancybox/jquery.fancybox-1.3.4.pack.js', array( 'jquery', 'wpp.localization' ), '1.7.3' );
        wp_register_script( 'wpp-jquery-colorpicker', WPP_URL . 'vendor/usabilitydynamics/lib-js-colorpicker/scripts/colorpicker.js', array( 'jquery', 'wpp.localization' ) );
        wp_register_script( 'wpp-jquery-easing', WPP_URL . 'third-party/fancybox/jquery.easing-1.3.pack.js', array( 'jquery', 'wpp.localization' ), '1.7.3' );
        wp_register_script( 'wpp-jquery-ajaxupload', WPP_URL . 'scripts/fileuploader.js', array( 'jquery', 'wpp.localization' ) );
        wp_register_script( 'wpp-jquery-nivo-slider', WPP_URL . 'third-party/jquery.nivo.slider.pack.js', array( 'jquery', 'wpp.localization' ) );
        wp_register_script( 'wpp-jquery-address', WPP_URL . 'scripts/jquery.address-1.5.js', array( 'jquery', 'wpp.localization' ) );
        wp_register_script( 'wpp-jquery-scrollTo', WPP_URL . 'scripts/jquery.scrollTo-min.js', array( 'jquery', 'wpp.localization' ) );
        wp_register_script( 'wpp-jquery-validate', WPP_URL . 'scripts/jquery.validate.js', array( 'jquery', 'wpp.localization' ) );
        wp_register_script( 'wpp-jquery-number-format', WPP_URL . 'scripts/jquery.number.format.js', array( 'jquery', 'wpp.localization' ) );
        wp_register_script( 'wpp-jquery-data-tables', WPP_URL . "vendor/datatables/datatables/media/js/jquery.dataTables.js", array( 'jquery', 'wpp.localization' ) );

        // Find and Register client-side JavaScript Library.
        if( file_exists( STYLESHEETPATH . '/wp_properties.js' ) ) {
          wp_register_script( 'wp-property-frontend', get_bloginfo( 'stylesheet_directory' ) . '/wp_properties.js', array( 'jquery-ui-core', 'wpp.localization' ), WPP_Version, true );
        } elseif( file_exists( TEMPLATEPATH . '/wp_properties.js' ) ) {
          wp_register_script( 'wp-property-frontend', get_bloginfo( 'template_url' ) . '/wp_properties.js', array( 'jquery-ui-core', 'wpp.localization' ), WPP_Version, true );
        } elseif( file_exists( WPP_Templates . '/wp_properties.js' ) ) {
          wp_register_script( 'wp-property-frontend', WPP_URL . 'templates/wp_properties.js', array( 'jquery-ui-core', 'wpp.localization' ), WPP_Version, true );
        }

        // Legacy Scripts for reference.
        // wp_register_script( 'jquery-cookie', WPP_URL . 'scripts/jquery.smookie.js', array( 'jquery', 'wpp.localization' ), '1.7.3' );
        // wp_register_script( 'wpp-md5', WPP_URL . 'third-party/md5.js', array( 'wpp.localization' ), WPP_Version );
        // wp_register_script( 'google-maps', 'https://maps.google.com/maps/api/js?sensor=true' );
        // wp_register_script( 'wpp-jquery-gmaps', WPP_URL . 'scripts/jquery.ui.map.min.js', array( 'google-maps', 'jquery-ui-core', 'jquery-ui-widget', 'wpp.localization' ) );

        //global $wp_scripts;
        //die( '<pre>' . print_r( $wp_scripts, true ) . '</pre>' );
      }

      /**
       * Find and Load Widgets.
       *
       */
      private function load_widgets() {

        add_action( 'widgets_init', array( &$this, 'widgets_init' ) );

      }

      /**
       * Find and Load Shortcodes.
       *
       */
      private function load_shortcodes() {

        // add_shortcode( 'property_search', array( $this, 'shortcode_property_search' ) );
        // add_shortcode( 'featured_properties', array( $this, 'shortcode_featured_properties' ) );
        // add_shortcode( 'property_map', array( $this, 'shortcode_property_map' ) );
        // add_shortcode( 'property_attribute', array( $this, 'shortcode_property_attribute' ) );

        // ? what is 'alternative_shortcodes'
        //if( !empty( $wp_properties[ 'alternative_shortcodes' ][ 'property_overview' ] ) ) {
          //add_shortcode( "{$wp_properties[ 'alternative_shortcodes' ]['property_overview']}", array( $this, 'shortcode_property_overview' ) );
        //}

        // Temp! It's just for testing
        include dirname( dirname( __FILE__ ) ) . '/test/shortcode.php';

        //global $_shortcodes; echo "<pre>"; print_r( $_shortcodes ); echo "</pre>"; die();

      }

      /**
       * Get Setting.
       *
       *    // Get Setting
       *    $wpp::get( 'my_key' )
       *
       * @method get
       *
       * @for Bootstrap
       * @author potanin@UD
       * @since 0.1.1
       */
      public function get( $key, $default = null ) {
        return self::$instance->_settings ? self::$instance->_settings->get( $key, $default ) : null;
      }

      /**
       * Set Setting.
       *
       * @usage
       *
       *    // Set Setting
       *    $wpp::set( 'my_key', 'my-value' )
       *
       * @method get
       * @for Bootstrap
       *
       * @author potanin@UD
       * @since 0.1.1
       */
      public function set( $key, $value = null ) {
        return self::$instance->_settings ? self::$instance->_settings->set( $key, $value ) : null;
      }

      /**
       * Add 'property' to the list of RSSable post_types.
       *
       * @param $qv
       *
       * @internal param string $request
       *
       * @return string
       * @author korotkov@ud
       * @since 1.36.2
       */
      public function property_feed( $qv ) {

        if( isset( $qv[ 'feed' ] ) && !isset( $qv[ 'post_type' ] ) ) {
          $qv[ 'post_type' ] = get_post_types( $args = array(
            'public'   => true,
            '_builtin' => false
          ) );
          array_push( $qv[ 'post_type' ], 'post' );
        }

        return $qv;

      }

      /**
       * Run on plugin activation.
       *
       * As of WP 3.1 this is not ran on automatic update.
       *
       * @todo Add check for cron usage via XMLI or other since cron.php is removed.
       *
       * @since 1.10
       */
      public function activation() {
        global $wp_rewrite;
        // Do close to nothing because only ran on activation, not updates, as of 3.1
        // Now handled by Utility::manual_activation().

        $wp_rewrite->flush_rules();
      }

      /**
       * Plugin Deactivation
       *
       */
      public function deactivation() {
        global $wp_rewrite;
        $timestamp = wp_next_scheduled( 'wpp_premium_feature_check' );
        wp_unschedule_event( $timestamp, 'wpp_premium_feature_check' );
        wp_clear_scheduled_hook( 'wpp_premium_feature_check' );

        $wp_rewrite->flush_rules();

      }

      /**
       * Renders a critical failure.
       *
       * @example
       *    self::fail( 'Critical plugin failure!' );
       *
       * @param $data
       */
      public function fail( $data ) {
        wp_die( '<h1>' . __( 'WP-Property Failure', 'wpp' ) . '</h1><p>' . $data . '</p>' );
      }

      /**
       * Adds thumbnail feature to WP-Property pages
       *
       * There is some weird issue with thie method.
       * If its named after_setup_theme and legacy.php extends Bootstrap with WPP_Core, this method loses context when triggered.
       *
       * @since 0.60
       */
      public function setup_theme() {

        // Determine if memory limit is low and increase it
        if( (int) ini_get( 'memory_limit' ) < 128 ) {
          ini_set( 'memory_limit', '128M' );
        }

        // Pre-init action hook
        do_action( 'wpp:setup_theme', $this );

        add_theme_support( 'post-thumbnails' );

      }

      /**
       * Called on init, as early as possible.
       *
       * @todo Ensure $this->settings_action() his is necessary.
       *
       * @since 1.11
       * @uses $wp_properties WP-Property configuration array
       * @access public
       */
      public function init_upper() {
        global $wp_properties;

        // Pre Initialization.
        do_action( 'wpp:init:pre', $this );

        //** Load languages */
        load_plugin_textdomain( self::$text_domain, WPP_Path . false, 'wp-property/languages' );

        // Register Property Post Type.
        $this->register_post_type();

        // Register Taxonomies.
        $this->register_taxonomies();

        // Define and Set WPP Capabilities.
        $this->set_capabilities();

        //** Add metaboxes hook */
        add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ) );

        // Initializer.
        do_action( 'wpp:init', $this );

      }

      /**
       * Setup widgets and widget areas.
       *
       * @since 1.31.0
       *
       */
      public function widgets_init() {
        global $wp_properties;

        // Loads Widgets.
        include_once WPP_Path . 'core/widgets/class-child-properties.php';
        include_once WPP_Path . 'core/widgets/class-featured-properties.php';
        include_once WPP_Path . 'core/widgets/class-gallery.php';
        include_once WPP_Path . 'core/widgets/class-latest-properties.php';
        include_once WPP_Path . 'core/widgets/class-other-properties.php';
        include_once WPP_Path . 'core/widgets/class-property-attributes.php';
        include_once WPP_Path . 'core/widgets/class-search-properties.php';

        if( class_exists( 'Property_Attributes_Widget' ) ) {
          register_widget( "Property_Attributes_Widget" );
        }

        if( class_exists( 'ChildPropertiesWidget' ) ) {
          register_widget( 'ChildPropertiesWidget' );
        }

        if( class_exists( 'SearchPropertiesWidget' ) ) {
          register_widget( "SearchPropertiesWidget" );
        }

        if( class_exists( 'FeaturedPropertiesWidget' ) ) {
          register_widget( "FeaturedPropertiesWidget" );
        }

        if( class_exists( 'GalleryPropertiesWidget' ) ) {
          register_widget( "GalleryPropertiesWidget" );
        }

        if( class_exists( 'LatestPropertiesWidget' ) ) {
          register_widget( "LatestPropertiesWidget" );
        }

        if( class_exists( 'OtherPropertiesWidget' ) ) {
          register_widget( "OtherPropertiesWidget" );
        }

        //** Register a sidebar for each property type */
        if( $wp_properties[ 'configuration' ][ 'do_not_register_sidebars' ] != 'true' ) {
          foreach( (array) $wp_properties[ 'property_types' ] as $property_slug => $property_title ) {
            register_sidebar( array(
              'name'          => sprintf( __( 'Property: %s', 'wpp' ), $property_title ),
              'id'            => "wpp_sidebar_{$property_slug}",
              'description'   => sprintf( __( 'Sidebar located on the %s page.', 'wpp' ), $property_title ),
              'before_widget' => '<li id="%1$s"  class="wpp_widget %2$s">',
              'after_widget'  => '</li>',
              'before_title'  => '<h3 class="widget-title">',
              'after_title'   => '</h3>',
            ));
          }
        }

      }

      /**
       * Secondary WPP Initialization ran towards the end of init()
       *
       * Loads things that we want make accessible for modification via other plugins.
       *
       * @since 1.31.0
       * @uses $wp_properties WP-Property configuration array
       * @access public
       *
       */
      public function init_lower() {
        global $wp_properties;



        /** Ajax functions */
        add_action( 'wp_ajax_wpp_ajax_max_set_property_type', create_function( "", ' die(Utility::mass_set_property_type($_REQUEST["property_type"]));' ) );
        add_action( 'wp_ajax_wpp_ajax_property_query', create_function( "", ' $class = Utility::get_property(trim($_REQUEST["property_id"])); if($class) { echo "Utility::get_property() output: \n\n"; print_r($class); echo "\nAfter prepare_property_for_display() filter:\n\n"; print_r(prepare_property_for_display($class));  } else { echo sprintf(__("No %1s found.","wpp"), Utility::property_label( "singular" ) );; } die();' ) );
        add_action( 'wp_ajax_wpp_ajax_image_query', create_function( "", ' $class = Utility::get_property_image_data($_REQUEST["image_id"]); if($class)  print_r($class); else echo __("No image found.","wpp"); die();' ) );
        add_action( 'wp_ajax_wpp_ajax_check_plugin_updates', create_function( "", '  echo Utility::check_plugin_updates(); die();' ) );
        add_action( 'wp_ajax_wpp_ajax_clear_cache', create_function( "", '  echo Utility::clear_cache(); die();' ) );
        add_action( 'wp_ajax_wpp_ajax_revalidate_all_addresses', create_function( "", '  echo Utility::revalidate_all_addresses(); die();' ) );
        add_action( 'wp_ajax_wpp_ajax_list_table', create_function( "", ' die(UsabilityDynamics\WPP\Utility::list_table());' ) );
        add_action( 'wp_ajax_wpp_save_settings', create_function( "", ' die(Utility::save_settings());' ) );

        /** Localization */
        add_action( "wp_ajax_wpp_js_localization", array( &$this, "localize_scripts" ) );
        add_action( "wp_ajax_nopriv_wpp_js_localization", array( &$this, "localize_scripts" ) );

        add_filter( "manage_edit-property_sortable_columns", array( &$this, "sortable_columns" ) );
        add_filter( "manage_edit-property_columns", array( &$this, "edit_columns" ) );

        /** Called in setup_postdata().  We add property values here to make available in global $post variable on frontend */
        add_action( 'the_post', array( 'UsabilityDynamics\WPP\Utility', 'the_post' ) );

        add_action( "the_content", array( &$this, "the_content" ) );

        /** Admin interface init */
        add_action( "admin_init", array( &$this, "admin_init" ) );
        add_action( "admin_menu", array( &$this, 'admin_menu' ) );

        add_action( "post_submitbox_misc_actions", array( &$this, "post_submitbox_misc_actions" ) );
        add_action( 'save_post', array( 'UsabilityDynamics\WPP\Listing', 'save' ) );

        add_action( 'before_delete_post', array( 'UsabilityDynamics\WPP\Utility', 'before_delete_post' ) );
        add_filter( 'post_updated_messages', array( &$this, 'property_updated_messages' ), 5 );

        /** Fix toggale row actions -> get rid of "Quick Edit" on property rows */
        add_filter( 'page_row_actions', array( &$this, 'property_row_actions' ), 0, 2 );

        /** Disables meta cache for property obejcts if enabled */
        add_action( 'pre_get_posts', array( 'UsabilityDynamics\WPP\Utility', 'pre_get_posts' ) );

        /** Fix 404 errors */
        add_filter( "parse_request", array( &$this, "parse_request" ) );

        //** Determines if current request is for a child property */
        add_filter( "posts_results", array( 'UsabilityDynamics\WPP\Utility', "posts_results" ) );

        //** Hack. Used to avoid issues of some WPP capabilities */
        add_filter( 'current_screen', array(  &$this, 'current_screen' ) );

        //** Load admin header scripts */
        add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );

        //** Check premium feature availability */
        add_action( 'wpp_premium_feature_check', array( &$this, 'feature_check' ) );

        //** Contextual Help */
        add_action( 'wpp_contextual_help', array( &$this, 'wpp_contextual_help' ) );

        //** Page loading handlers */
        add_action( 'load-property_page_all_properties', array( 'UsabilityDynamics\WPP\Utility', 'property_page_all_properties_load' ) );
        add_action( 'load-property_page_property_settings', array( 'UsabilityDynamics\WPP\Utility', 'property_page_property_settings_load' ) );
        add_filter( "wpp_overview_columns", array( 'UsabilityDynamics\WPP\Utility', 'custom_attribute_columns' ) );
        add_filter( "wpp_attribute_filter", array( 'UsabilityDynamics\WPP\Utility', 'attribute_filter' ), 10, 2 );
        add_filter( "manage_property_page_all_properties_columns", array( 'UsabilityDynamics\WPP\Utility', 'overview_columns' ) );

        //** Add custom image sizes */
        foreach( (array) $wp_properties[ 'image_sizes' ] as $image_name => $image_sizes ) {
          add_image_size( $image_name, $image_sizes[ 'width' ], $image_sizes[ 'height' ], true );
        }

        //** Add troubleshoot log page */
        if( isset( $wp_properties[ 'configuration' ][ 'show_ud_log' ] ) && $wp_properties[ 'configuration' ][ 'show_ud_log' ] == 'true' ) {
          // Utility::add_log_page();
        }

        //** Modify admin body class */
        add_filter( 'admin_body_class', array( &$this, 'admin_body_class' ), 5 );

        // Frontend Body Class.
        add_filter( 'body_class', array( 'UsabilityDynamics\WPP\Theme', 'body_class' ) );

        add_filter( 'wp_get_attachment_link', array( 'UsabilityDynamics\WPP\Utility', 'wp_get_attachment_link' ), 10, 6 );

        // Toggle Property Featured Status.
        if( isset( $_REQUEST[ '_wpnonce' ] ) &&  wp_verify_nonce( $_REQUEST[ '_wpnonce' ], "wpp_make_featured_" . $_REQUEST[ 'post_id' ] ) ) {
          add_action( 'wp_ajax_wpp_make_featured', create_function( "", '  $post_id = $_REQUEST[post_id]; echo Utility::toggle_featured($post_id); die();' ) );
        }

        // Post Init Action.
        do_action( 'wpp:init:post', $this );

      }

      /**
       * Performs front-end pre-header functionality
       *
       * This function is not called on amdin side
       * Loads conditional CSS styles
       *
       * @since 1.11
       */
      public function template_redirect() {
        global $post, $property, $wp_query, $wp_properties, $wp_styles, $wpp_query, $wp_taxonomies;

        wp_localize_script( 'wpp.localization', 'wpp', array( 'instance' => $this->locale_instance() ) );

        //** Load global wp-property script on all frontend pages */
        wp_enqueue_script( 'wpp.global' );

        //** Load essential styles that are used in widgets */
        wp_enqueue_style( 'wp-property-frontend' );
        wp_enqueue_style( 'wp-property-theme-specific' );

        //** Load non-essential scripts and styles if option is enabled to load them globally */
        if( $wp_properties[ 'configuration' ][ 'load_scripts_everywhere' ] == 'true' ) {
          Utility::console_log( 'Loading WP-Property scripts globally.' );
          Utility::load_assets( array( 'single', 'overview' ) );
        }

        if( $wp_properties[ 'configuration' ][ 'do_not_enable_text_widget_shortcodes' ] != 'true' ) {
          add_filter( 'widget_text', 'do_shortcode' );
        }

        do_action( 'wpp_template_redirect' );

        //** Handle single property page previews */
        if( !empty( $wp_query->query_vars[ 'preview' ] ) && $post->post_type == "property" && $post->post_status == "publish" ) {
          wp_redirect( get_permalink( $post->ID ) );
          die();
        }

        if( count( $wp_query->posts ) < 2 && ( $post->post_type == "property" || $wp_query->is_child_property ) ) {
          $wp_query->single_property_page = true;

          //** This is a hack and should be done better */
          if( !$post ) {
            $post                 = get_post( $wp_query->queried_object_id );
            $wp_query->posts[ 0 ] = $post;
            $wp_query->post       = $post;
          }
        }

        //** Monitor taxonomy archive queries */
        if( is_tax() && in_array( $wp_query->query_vars[ 'taxonomy' ], array_keys( (array) $wp_taxonomies ) ) ) {
          //** Once get_properties(); can accept taxonomy searches, we can inject a search request in here */
        }

        //** If viewing root property page that is the default dynamic page. */
        if( $wp_query->wpp_default_property_page ) {
          $wp_query->is_property_overview = true;
        }

        //** If this is the root page with a manually inserted shortcode, or any page with a PO shortcode */
        if( strpos( $post->post_content, "property_overview" ) ) {
          $wp_query->is_property_overview = true;
        }

        //** If this is the root page and the shortcode is automatically inserted */
        if( $wp_query->wpp_root_property_page && $wp_properties[ 'configuration' ][ 'automatically_insert_overview' ] == 'true' ) {
          $wp_query->is_property_overview = true;
        }

        //** If search result page, and system not explicitly configured to not include PO on search result page automatically */
        if( $wp_query->wpp_search_page && $wp_properties[ 'configuration' ][ 'do_not_override_search_result_page' ] != 'true' ) {
          $wp_query->is_property_overview = true;
        }

        //** Scripts and styles to load on all overview and signle listing pages */
        if( $wp_query->single_property_page || $wp_query->is_property_overview ) {

          Utility::console_log( 'Including scripts for all single and overview property pages.' );

          Utility::load_assets( array( 'single', 'overview' ) );

          // Check for and load conditional browser styles
          $conditional_styles = apply_filters( 'wpp_conditional_style_slugs', array( 'IE', 'IE 7', 'msie' ) );

          foreach( (array) $conditional_styles as $type ) {

            // Fix slug for URL
            $url_slug = strtolower( str_replace( " ", "_", $type ) );

            if( file_exists( STYLESHEETPATH . "/wp_properties-{$url_slug}.css" ) ) {
              wp_register_style( 'wp-property-frontend-' . $url_slug, get_bloginfo( 'stylesheet_directory' ) . "/wp_properties-{$url_slug}.css", array( 'wp-property-frontend' ), '1.13' );
            } elseif( file_exists( TEMPLATEPATH . "/wp_properties-{$url_slug}.css" ) ) {
              wp_register_style( 'wp-property-frontend-' . $url_slug, get_bloginfo( 'template_url' ) . "/wp_properties-{$url_slug}.css", array( 'wp-property-frontend' ), '1.13' );
            } elseif( file_exists( WPP_Templates . "/wp_properties-{$url_slug}.css" ) && $wp_properties[ 'configuration' ][ 'autoload_css' ] == 'true' ) {
              wp_register_style( 'wp-property-frontend-' . $url_slug, WPP_URL . "templates/wp_properties-{$url_slug}.css", array( 'wp-property-frontend' ), WPP_Version );
            }
            // Mark every style as conditional
            $wp_styles->add_data( 'wp-property-frontend-' . $url_slug, 'conditional', $type );
            wp_enqueue_style( 'wp-property-frontend-' . $url_slug );

          }

        }

        //** Scripts loaded only on single property pages */
        if( $wp_query->single_property_page && !post_password_required( $post ) ) {

          Utility::console_log( 'Including scripts for all single property pages.' );

          Utility::load_assets( array( 'single' ) );

          do_action( 'template_redirect_single_property' );

          add_action( 'wp_head', create_function( '', "do_action('wp_head_single_property'); " ) );

          $property = Utility::get_property( $post->ID, "load_gallery=true" );

          $property = prepare_property_for_display( $property );

          $type = $property[ 'property_type' ];

          //** Make certain variables available to be used within the single listing page */
          $single_page_vars = apply_filters( 'wpp_property_page_vars', array(
            'property'      => $property,
            'wp_properties' => $wp_properties
          ) );

          //** By merging our extra variables into $wp_query->query_vars they will be extracted in load_template() */
          if( is_array( $single_page_vars ) ) {
            $wp_query->query_vars = array_merge( $wp_query->query_vars, $single_page_vars );
          }

          $template_found = Utility::get_template_part( array(
            "property-{$type}",
            "property",
          ), array( WPP_Templates ) );

          //** Load the first found template */
          if( $template_found ) {
            Utility::console_log( 'Found single property page template:' . $template_found );
            load_template( $template_found );
            die();
          }

        }

        //** Current requests includes a property overview.  PO may be via shortcode, search result, or due to this being the Default Dynamic Property page */
        if( $wp_query->is_property_overview ) {

          Utility::console_log( 'Including scripts for all property overview pages.' );

          if( $wp_query->wpp_default_property_page ) {
            Utility::console_log( 'Dynamic Default Property page detected, will load custom template.' );
          } else {
            Utility::console_log( 'Custom Default Property page detected, property overview content may be rendered via shortcode.' );
          }

          //** Make certain variables available to be used within the single listing page */
          $overview_page_vars = apply_filters( 'wpp_overview_page_vars', array(
            'wp_properties' => $wp_properties,
            'wpp_query'     => $wpp_query
          ) );

          //** By merging our extra variables into $wp_query->query_vars they will be extracted in load_template() */
          if( is_array( $overview_page_vars ) ) {
            $wp_query->query_vars = array_merge( $wp_query->query_vars, $overview_page_vars );
          }

          do_action( 'template_redirect_property_overview' );

          add_action( 'wp_head', create_function( '', "do_action('wp_head_property_overview'); " ) );

          //** If using Dynamic Property Root page, we must load a template */
          if( $wp_query->wpp_default_property_page ) {

            //** Unset any post that may have been found based on query */
            $post = false;

            $template_found = Utility::get_template_part( array(
              "property-search-result",
              "property-overview-page",
            ), array( WPP_Templates ) );

            //** Load the first found template */
            if( $template_found ) {
              Utility::console_log( 'Found Default property overview page template:' . $template_found );
              load_template( $template_found );
              die();
            }

          }

        }

        do_action( 'wpp_template_redirect_post_scripts' );

      }

      /**
       * Runs pre-header functions on admin-side only
       *
       * Checks if plugin has been updated.
       *
       * @since 1.10
       *
       */
      public function admin_init() {
        global $wp_properties, $post;

        //include_once( '/Users/potanin/Sites/property.cluster.veneer.io/vendor/wordpress/core/wp-admin/includes/class-wp-upgrader-skins.php' );
        //include_once( '/Users/potanin/Sites/property.cluster.veneer.io/vendor/wordpress/core/wp-admin/includes/misc.php' );
        //include_once( '/Users/potanin/Sites/property.cluster.veneer.io/vendor/wordpress/core/wp-admin/includes/class-wp-upgrader.php' );
        //include_once( '/Users/potanin/Sites/property.cluster.veneer.io/vendor/wordpress/core/wp-admin/includes/file.php' );

        Utility::fix_screen_options();

        // Plug page actions -> Add Settings Link to plugin overview page
        add_filter( 'plugin_action_links', array( &$this, 'plugin_action_links' ), 10, 2 );

        //* Adds metabox 'General Information' to Property Edit Page */
        add_meta_box( 'wpp_property_meta', __( 'General Information', 'wpp' ), array( '\UsabilityDynamics\WPP\UI', 'metabox_meta' ), 'property', 'normal', 'high' );

        //* Adds 'Group' metaboxes to Property Edit Page */
        if( !empty( $wp_properties[ 'property_groups' ] ) ) {
          foreach( (array) $wp_properties[ 'property_groups' ] as $slug => $group ) {
            //* There is no sense to add metabox if no one attribute assigned to group */
            if( !in_array( $slug, $wp_properties[ 'property_stats_groups' ] ) ) {
              continue;
            }
            //* Determine if Group name is empty we add 'NO NAME', other way metabox will not be added */
            if( empty( $group[ 'name' ] ) ) {
              $group[ 'name' ] = __( 'NO NAME', 'wpp' );
            }
            add_meta_box( $slug, __( $group[ 'name' ], 'wpp' ), array( '\UsabilityDynamics\WPP\UI', 'metabox_meta' ), 'property', 'normal', 'high', array( 'group' => $slug ) );
          }
        }

        add_meta_box( 'propetry_filter', $wp_properties[ 'labels' ][ 'name' ] . ' ' . __( 'Search', 'wpp' ), array( 'UsabilityDynamics\WPP\UI', 'metabox_property_filter' ), 'property_page_all_properties', 'normal' );

        // Add Metaboxes.
        do_action( 'wpp:metaboxes', $this );

        self::manual_activation();

        // Handle Settings Download.
        if( $_REQUEST[ 'page' ] == 'property_settings' && $_REQUEST[ 'wpp_action' ] == 'download-wpp-backup' && wp_verify_nonce( $_REQUEST[ '_wpnonce' ], 'download-wpp-backup' ) ) {

          $this->_settings->file_transfer(array(
            "format" => "json",
            "name" => sanitize_key( get_bloginfo( 'name' ) ) . '-wp-property',
            "charset" => get_option( 'blog_charset' )
          ));

        }

      }

      /**
       * Register metaboxes.
       *
       * @global type $post
       * @global type $wpdb
       */
      public function add_meta_boxes() {
        global $post, $wpdb;

        //include_once( WPP_Path . '/test/meta-box.php' );

        //** Add metabox for child properties */
        if( $post->post_type == 'property' && $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent = '{$post->ID}' AND post_status = 'publish' " ) ) {
          add_meta_box( 'wpp_property_children', sprintf( __( 'Child %1s', 'wpp' ), Utility::property_label( 'plural' ) ), array( '\UsabilityDynamics\WPP\UI', 'child_properties' ), 'property', 'side', 'high' );
        }

      }

      /**
       * Check if WP-Property RaaS Active
       *
       * @return bool
       */
      public function is_active() {
        return true;
      }

      /**
       * Checks updates for premium features by AJAX
       * Prints results to body.
       *
       * @global array $wp_properties
       * @return null
       */
      public function check_plugin_updates() {
        global $wp_properties;

        $result = Utility::feature_check();

        if( is_wp_error( $result ) ) {
          printf( __( 'An error occurred during premium feature check: <b> %s </b>.', 'wpp' ), $result->get_error_message() );
        } else {
          echo $result;
        }

        return null;
      }

      /**
       * Run manually when a version mismatch is detected.
       *
       * Holds official current version designation.
       * Called in admin_init hook.
       *
       * @since 1.10
       * @version 1.13
       *
       */
      public function manual_activation() {

        $installed_ver = get_option( "wpp_version", 0 );
        $wpp_version   = WPP_Version;

        if( @version_compare( $installed_ver, $wpp_version ) == '-1' ) {
          // We are upgrading.

          // Unschedule event
          $timestamp = wp_next_scheduled( 'wpp_premium_feature_check' );
          wp_unschedule_event( $timestamp, 'wpp_premium_feature_check' );
          wp_clear_scheduled_hook( 'wpp_premium_feature_check' );

          // Schedule event
          wp_schedule_event( time(), 'daily', 'wpp_premium_feature_check' );

          //** Upgrade data if needed */
          self::upgrade();

          // Update option to latest version so this isn't run on next admin page load
          update_option( "wpp_version", $wpp_version );

          // Get premium features on activation
          @Utility::feature_check();

        }

        return;

      }

      /**
       * Moved from WPP_Legacy
       *
       */
      public function upgrade() {
        global $wpdb;

        $installed_ver = get_option( "wpp_version", 0 );
        $wpp_version   = WPP_Version;

        if( @version_compare( $installed_ver, WPP_Version ) == '-1' ) {

          switch( $installed_ver ) {

            /**
             * Upgrade:
             * - WPP postmeta data were saved to database with '&ndash;' instead of '-' in value. Function encode_sql_input was modified and it doesn't change '-' to '&ndash' anymore
             * So to prevent search result issues we need to update database data.
             * peshkov@UD
             */
            case ( version_compare( $installed_ver, '1.37.4' ) == '-1' ):

              $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_value = REPLACE( meta_value, '&ndash;', '-')" );

              break;

          }

        }

      }

      /**
       * Adds "Settings" link to the plugin overview page
       *
       *  *
       * @since 0.60
       *
       */
      public function plugin_action_links( $links, $file ) {

        if( $file == 'wp-property/wp-property.php' ) {
          $settings_link = '<a href="' . admin_url( "edit.php?post_type=property&page=property_settings" ) . '">' . __( 'Settings', 'wpp' ) . '</a>';
          array_unshift( $links, $settings_link ); // before other links
        }

        return $links;
      }

      /**
       * Can enqueue scripts on specific pages, and print content into head
       *
       *
       * @uses $current_screen global variable
       * @since 0.53
       *
       */
      public function admin_enqueue_scripts( $hook ) {
        global $current_screen, $wp_properties, $wpdb;

        wp_localize_script( 'wpp.localization', 'wpp', array(
          'instance' => $this->locale_instance()
        ));

        switch( $current_screen->id ) {

          //** Property Overview Page and Edit Property page */
          case 'property_page_all_properties':
            wp_enqueue_script( 'wpp.admin' );
            wp_enqueue_script( 'wpp.admin.overview' );

          case 'property':
            wp_enqueue_script( 'wpp.global' );
            //** Enabldes fancybox js, css and loads overview scripts */
            wp_enqueue_script( 'post' );
            wp_enqueue_script( 'postbox' );
            wp_enqueue_script( 'wpp-jquery-fancybox' );
            wp_enqueue_script( 'wpp-jquery-data-tables' );
            wp_enqueue_style( 'wpp-jquery-fancybox-css' );
            wp_enqueue_style( 'wpp-jquery-data-tables' );
            //** Get width of overview table thumbnail, and set css */
            $thumbnail_attribs = Utility::image_sizes( $wp_properties[ 'configuration' ][ 'admin_ui' ][ 'overview_table_thumbnail_size' ] );
            $thumbnail_width   = ( !empty( $thumbnail_attribs[ 'width' ] ) ? $thumbnail_attribs[ 'width' ] : false );

            if( $thumbnail_width ) { ?>
              <style typ="text/css">
                #wp-list-table.wp-list-table .column-thumbnail {
                  width: <?php echo $thumbnail_width + 20; ?>px;
                }

                #wp-list-table.wp-list-table td.column-thumbnail {
                  text-align: right;
                }

                #wp-list-table.wp-list-table .column-type {
                  width: 90px;
                }

                #wp-list-table.wp-list-table .column-menu_order {
                  width: 50px;
                }

                #wp-list-table.wp-list-table td.column-menu_order {
                  text-align: center;
                }

                #wp-list-table.wp-list-table .column-featured {
                  width: 100px;
                }

                #wp-list-table.wp-list-table .check-column {
                  width: 26px;
                }
              </style>
            <?php }

            break;

          //** Settings Page */
          case 'property_page_property_settings':
            wp_enqueue_script( 'wpp.admin' );
            wp_enqueue_script( 'wpp.global' );
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui-core' );
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'wpp-jquery-colorpicker' );
            wp_enqueue_script( 'wpp.admin.settings' );
            wp_enqueue_style( 'wpp-jquery-colorpicker-css' );
            break;

          //** Widgets Page */
          case 'widgets':
            wp_enqueue_script( 'wpp.admin' );
            wp_enqueue_script( 'wpp.global' );
            wp_enqueue_script( 'jquery-ui-core' );
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'jquery-ui-tabs' );
            wp_enqueue_style( 'jquery-ui' );
            wp_enqueue_script( 'wpp.admin.widgets' );
            break;

        }

        //** Automatically insert styles sheet if one exists with $current_screen->ID name */
        if( file_exists( WPP_Path . "/styles/{$current_screen->id}.css" ) ) {
          wp_enqueue_style( $current_screen->id . '-style', WPP_URL . "/styles/{$current_screen->id}.css", array(), WPP_Version, 'screen' );
        }

        //** Automatically insert JS sheet if one exists with $current_screen->ID name */
        if( file_exists( WPP_Path . "scripts/{$current_screen->id}.js" ) ) {
          wp_enqueue_script( $current_screen->id . '-js', WPP_URL . "scripts/{$current_screen->id}.js", array( 'jquery' ), WPP_Version, 'wpp.admin' );
        }

        // Enqueue Admin CSS on all backend pages.
        if( file_exists( WPP_Path . 'styles/wpp-admin.css' ) ) {
          wp_enqueue_style( 'wpp-admin', WPP_URL . 'styles/wpp-admin.css' );
        }

      }

      /**
       * Sets up additional pages and loads their scripts
       *
       * @since 0.5
       *
       */
      public function admin_menu() {
        global $wp_properties, $submenu;

        // Dashboard Page.
        // $dashboard_page   = add_submenu_page( 'edit.php?post_type=property', __( 'Dashboard', 'wpp' ), __( 'Dashboard', 'wpp' ), 'manage_wpp_dashboard', 'dashboard', create_function( '', 'global $wp_properties; include "ui/page-dashboard.php";' ) );

        // Modules Page.
        $modules_page   = add_submenu_page( 'edit.php?post_type=property', __( 'Modules', 'wpp' ), __( 'Modules', 'wpp' ), 'manage_wpp_modules', 'modules', create_function( '', 'global $wp_properties; include "ui/page-modules.php";' ) );

        // Settings Page.
        $settings_page  = add_submenu_page( 'edit.php?post_type=property', __( 'Settings', 'wpp' ), __( 'Settings', 'wpp' ), 'manage_wpp_settings', 'property_settings', create_function( '', 'global $wp_properties; include "ui/page-settings.php";' ) );

        // All Properties Overview Page.
        $all_properties = add_submenu_page( 'edit.php?post_type=property', $wp_properties[ 'labels' ][ 'all_items' ], $wp_properties[ 'labels' ][ 'all_items' ], 'edit_wpp_properties', 'all_properties', create_function( '', 'global $wp_properties, $screen_layout_columns; include "ui/page-properties.php";' ) );

        /**
         * Next used to add custom submenu page 'All Properties' with Javascript dataTable
         *
         * @author korotkov@UD
         */
        if( !empty( $submenu[ 'edit.php?post_type=property' ] ) ) {

          //** Comment next line if you want to get back old Property list page. */
          array_shift( $submenu[ 'edit.php?post_type=property' ] );

          foreach( (array) $submenu[ 'edit.php?post_type=property' ] as $key => $page ) {
            if( $page[ 2 ] == 'all_properties' ) {
              unset( $submenu[ 'edit.php?post_type=property' ][ $key ] );
              array_unshift( $submenu[ 'edit.php?post_type=property' ], $page );
            } elseif( $page[ 2 ] == 'post-new.php?post_type=property' ) {
              //** Removes 'Add Property' from menu if user can not edit properties. peshkov@UD */
              if( !current_user_can( 'edit_wpp_property' ) ) {
                unset( $submenu[ 'edit.php?post_type=property' ][ $key ] );
              }
            }
          }
        }

        do_action( 'wpp_admin_menu', $this );
        do_action( 'wpp:admin_menu', $this );

        // Load jQuery UI Tabs and Cookie into settings page (settings_page_property_settings)
        add_action( 'admin_print_scripts-' . $settings_page, create_function( '', "wp_enqueue_script('jquery-ui-tabs');wp_enqueue_script('jquery-cookie');" ) );
        add_action( 'admin_print_scripts-' . $modules_page, create_function( '', "wp_enqueue_script('jquery-ui-tabs');wp_enqueue_script('jquery-cookie');" ) );
        add_action( 'admin_print_scripts-' . $all_properties, create_function( '', "wp_enqueue_script('jquery-ui-tabs');wp_enqueue_script('jquery-cookie');" ) );

      }

      /**
       * Modify admin body class on property pages for CSS
       *
       * @todo $current_screen does not seem to work in 3.8.
       *
       * @since 0.5
       */
      public function admin_body_class( $admin_body_class ) {
        global $current_screen;

        $classes = explode( ' ', trim( $admin_body_class ) );

        $classes[ ] = self::is_active() ? 'wpp-connected' : 'wpp-disconnected';

        if( $current_screen->id == 'edit-property' ) {
          $classes[ ] = 'wpp_property_edit';
        }

        if( $current_screen->id == 'property' ) {
          $classes[ ] = 'wpp_property_edit';
        }

        return implode( ' ', array_unique( $classes ) );

      }

      /**
       * Fixed property pages being seen as 404 pages
       *
       * Ran on parse_request;
       *
       * WP handle_404() function decides if current request should be a 404 page
       * Marking the global variable $wp_query->is_search to true makes the function
       * assume that the request is a search.
       *
       * @param $query
       *
       * @since 0.5
       */
      public function parse_request( $query ) {
        global $wp, $wp_query, $wp_properties, $wpdb;

        //** If we don't have permalinks, our base slug is always default */
        if( get_option( 'permalink_structure' ) == '' ) {
          $wp_properties[ 'configuration' ][ 'base_slug' ] = 'property';
        }

        //** If we are displaying search results, we can assume this is the default property page */
        if( is_array( $_REQUEST[ 'wpp_search' ] ) ) {

          if( isset( $_POST[ 'wpp_search' ] ) ) {
            $query = '?' . http_build_query( array( 'wpp_search' => $_REQUEST[ 'wpp_search' ] ), '', '&' );
            wp_redirect( Utility::base_url( $wp_properties[ 'configuration' ][ 'base_slug' ] ) . $query );
            die();
          }

          $wp_query->wpp_root_property_page = true;
          $wp_query->wpp_search_page        = true;
        }

        //** Determine if this is the Default Property Page */

        if( isset( $wp_properties[ 'configuration' ][ 'base_slug' ] ) && $wp->request == $wp_properties[ 'configuration' ][ 'base_slug' ] ) {
          $wp_query->wpp_root_property_page = true;
        }

        if( !empty( $wp_properties[ 'configuration' ][ 'base_slug' ] ) && $wp->query_string == "p=" . $wp_properties[ 'configuration' ][ 'base_slug' ] ) {
          $wp_query->wpp_root_property_page = true;
        }

        if( isset( $query->query_vars[ 'name' ] ) && $query->query_vars[ 'name' ] == $wp_properties[ 'configuration' ][ 'base_slug' ] ) {
          $wp_query->wpp_root_property_page = true;
        }

        if( isset( $query->query_vars[ 'pagename' ] ) && $query->query_vars[ 'pagename' ] == $wp_properties[ 'configuration' ][ 'base_slug' ] ) {
          $wp_query->wpp_root_property_page = true;
        }

        if( isset( $query->query_vars[ 'category_name' ] ) && $query->query_vars[ 'category_name' ] == $wp_properties[ 'configuration' ][ 'base_slug' ] ) {
          $wp_query->wpp_root_property_page = true;
        }

        //** If this is a the root property page, and the Dynamic Default Property page is used */
        if( $wp_query->wpp_root_property_page && $wp_properties[ 'configuration' ][ 'base_slug' ] == 'property' ) {
          $wp_query->wpp_default_property_page = true;

          Utility::console_log( 'Overriding default 404 page status.' );

          /** Set to override the 404 status */
          add_action( 'wp', create_function( '', 'status_header( 200 );' ) );

          //** Prevent is_404() in template files from returning true */
          add_action( 'template_redirect', create_function( '', ' global $wp_query; $wp_query->is_404 = false;' ), 0, 10 );
        }

        if( $wp_query->wpp_search_page ) {
          $wpp_pages[ ] = 'Search Page';
        }

        if( $wp_query->wpp_default_property_page ) {
          $wpp_pages[ ] = 'Default Property Page';
        }

        if( $wp_query->wpp_root_property_page ) {
          $wpp_pages[ ] = 'Root Property Page.';
        }

        if( is_array( $wpp_pages ) ) {
          Utility::console_log( 'Utility::parse_request() ran, determined that request is for: ' . implode( ', ', $wpp_pages ) );
        }

      }

      /**
       * Modifies post content
       *
       * @since 1.04
       *
       */
      public function the_content( $content ) {
        global $post, $wp_properties, $wp_query;

        if( !isset( $wp_query->is_property_overview ) ) {
          return $content;
        }

        //** Handle automatic PO inserting for non-search root page */
        if( !$wp_query->wpp_search_page && $wp_query->wpp_root_property_page && $wp_properties[ 'configuration' ][ 'automatically_insert_overview' ] == 'true' ) {
          Utility::console_log( 'Automatically inserted property overview shortcode into page content.' );

          return self::shortcode_property_overview();
        }

        //** Handle automatic PO inserting for search pages */
        if( $wp_query->wpp_search_page && $wp_properties[ 'configuration' ][ 'do_not_override_search_result_page' ] != 'true' ) {
          Utility::console_log( 'Automatically inserted property overview shortcode into search page content.' );

          return self::shortcode_property_overview();
        }

        return $content;
      }

      /**
       * Inserts content into the "Publish" metabox on property pages
       *
       * @since 1.04
       *
       */
      public function post_submitbox_misc_actions() {
        global $post, $wp_properties;

        if( $post->post_type == 'property' ) {

          ?>
          <div class="misc-pub-section ">

        <ul>
          <li><?php _e( 'Menu Sort Order:', 'wpp' ) ?> <?php echo Utility::input( "name=menu_order&special=size=4", $post->menu_order ); ?></li>

          <?php if( current_user_can( 'manage_options' ) && $wp_properties[ 'configuration' ][ 'do_not_use' ][ 'featured' ] != 'true' ) { ?>
            <li><?php echo Utility::checkbox( "name=wpp_data[meta][featured]&label=" . __( 'Display in featured listings.', 'wpp' ), get_post_meta( $post->ID, 'featured', true ) ); ?></li>
          <?php } ?>

          <?php do_action( 'wpp_publish_box_options' ); ?>
        </ul>

      </div>
        <?php

        }

        return;

      }

      /**
       * Removes "quick edit" link on property type objects
       *
       * Called in via page_row_actions filter
       *
       * @since 0.5
       *
       */
      public function property_row_actions( $actions, $post ) {

        if( $post->post_type != 'property' )
          return $actions;

        unset( $actions[ 'inline' ] );

        return $actions;
      }

      /**
       * Adds property-relevant messages to the property post type object
       *
       *
       * @since 0.5
       *
       */
      public function property_updated_messages( $messages ) {
        global $post_id, $post;

        $messages[ 'property' ] = array(
          0  => '', // Unused. Messages start at index 1.
          1  => sprintf( __( '%2s updated. <a href="%s">view %1s</a>', 'wpp' ), Utility::property_label( 'singular' ), esc_url( get_permalink( $post_id ) ), Utility::property_label( 'singular' ) ),
          2  => __( 'Custom field updated.', 'wpp' ),
          3  => __( 'Custom field deleted.', 'wpp' ),
          4  => sprintf( __( '%1s updated.', 'wpp' ), Utility::property_label( 'singular' ) ),
          /* translators: %s: date and time of the revision */
          5  => isset( $_GET[ 'revision' ] ) ? sprintf( __( '%1s restored to revision from %s', 'wpp' ), Utility::property_label( 'singular' ), wp_post_revision_title( (int) $_GET[ 'revision' ], false ) ) : false,
          6  => sprintf( __( '%1s published. <a href="%s">View %2s</a>', 'wpp' ), Utility::property_label( 'singular' ), esc_url( get_permalink( $post_id ) ), Utility::property_label( 'singular' ) ),
          7  => sprintf( __( '%1s saved.', 'wpp' ), Utility::property_label( 'singular' ) ),
          8  => sprintf( __( '%1s submitted. <a target="_blank" href="%s">Preview %2s</a>', 'wpp' ), Utility::property_label( 'singular' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_id ) ) ), Utility::property_label( 'singular' ) ),
          9  => sprintf( __( '%1s scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview %2s</a>', 'wpp' ),
            // translators: Publish box date format, see http://php.net/date
            Utility::property_label( 'singular' ),
            date_i18n( __( 'M j, Y @ G:i', 'wpp' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_id ) ), Utility::property_label( 'singular' ) ),
          10 => sprintf( __( '%1s draft updated. <a target="_blank" href="%s">Preview %2s</a>', 'wpp' ), Utility::property_label( 'singular' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_id ) ) ), Utility::property_label( 'singular' ) ),
        );

        $messages = apply_filters( 'wpp_updated_messages', $messages );

        return $messages;
      }

      /**
       * Sets up property-type columns
       *
       * @since 0.54
       * @uses $wp_properties WP-Property configuration array
       * @access public
       *
       */
      public function edit_columns( $columns ) {
        global $wp_properties;

        unset( $columns );

        $columns[ 'cb' ]            = "<input type=\"checkbox\" />";
        $columns[ 'title' ]         = __( 'Title', 'wpp' );
        $columns[ 'property_type' ] = __( 'Type', 'wpp' );

        if( is_array( $wp_properties[ 'property_stats' ] ) ) {
          foreach( (array) $wp_properties[ 'property_stats' ] as $slug => $title )
            $columns[ $slug ] = $title;
        } else {
          $columns = $columns;
        }

        $columns[ 'city' ]       = __( 'City', 'wpp' );
        $columns[ 'overview' ]   = __( 'Overview', 'wpp' );
        $columns[ 'featured' ]   = __( 'Featured', 'wpp' );
        $columns[ 'menu_order' ] = __( 'Order', 'wpp' );
        $columns[ 'thumbnail' ]  = __( 'Thumbnail', 'wpp' );

        $columns = apply_filters( 'wpp_admin_overview_columns', $columns );

        //
        return $columns;
      }

      /**
       * Sets up sortable columns columns
       *
       * @since 1.08
       *
       */
      public function sortable_columns( $columns ) {
        global $wp_properties;

        $columns[ 'type' ]     = 'type';
        $columns[ 'featured' ] = 'featured';

        if( is_array( $wp_properties[ 'property_stats' ] ) ) {
          foreach( (array) $wp_properties[ 'property_stats' ] as $slug => $title )
            $columns[ $slug ] = $slug;
        }

        $columns = apply_filters( 'wpp_admin_sortable_columns', $columns );

        return $columns;
      }

      /**
       * Checks settings data on accord with existing wp_properties data ( before option updates )
       *
       * @param array $wpp_settings New wpp settings data
       * @param array $wp_properties Old wpp settings data
       *
       * @return array $wpp_settings
       */
      public function check_wp_settings_data( $wpp_settings, $wp_properties ) {
        if( is_array( $wpp_settings ) && is_array( $wp_properties ) ) {
          foreach( (array) $wp_properties as $key => $value ) {
            if( !isset( $wpp_settings[ $key ] ) ) {
              switch( $key ) {
                case 'hidden_attributes':
                case 'property_inheritance':
                  $wpp_settings[ $key ] = array();
                  break;
              }
            }
          }
        }

        return $wpp_settings;
      }

      /**
       * Hack to avoid issues with capabilities and views.
       *
       */
      public function current_screen( $screen ) {

        // property_page_all_properties
        // property_page_property_settings
        // property_page_features

        switch( $screen->id ) {
          case "edit-property":
            wp_redirect( 'edit.php?post_type=property&page=all_properties' );
            exit();
            break;
        }

        return $screen;
      }

      /**
       * Adds all WPP custom capabilities to administrator role.
       * Premium feature capabilities are added by filter in this function, see below.
       *
       * @author peshkov@UD
       */
      public function set_capabilities() {
        global $wpp_capabilities;

        //* Get Administrator role for adding custom capabilities */
        $role =& get_role( 'administrator' );

        //* General WPP capabilities */
        $wpp_capabilities = array(

          //* Manage WPP Properties Capabilities */
          'edit_wpp_properties'        => sprintf( __( 'View %1s', 'wpp' ), Utility::property_label( 'plural' ) ),
          'edit_wpp_property'          => sprintf( __( 'Add/Edit %1s', 'wpp' ), Utility::property_label( 'plural' ) ),
          'edit_others_wpp_properties' => sprintf( __( 'Edit Other %1s', 'wpp' ), Utility::property_label( 'plural' ) ),
          //'read_wpp_property' => __( 'Read Property', 'wpp' ),
          'delete_wpp_property'        => sprintf( __( 'Delete %1s', 'wpp' ), Utility::property_label( 'plural' ) ),
          'publish_wpp_properties'     => sprintf( __( 'Publish %1s', 'wpp' ), Utility::property_label( 'plural' ) ),
          //'read_private_wpp_properties' => __( 'Read Private Properties', 'wpp' ),

          //* WPP Settings capability */
          'manage_wpp_settings'        => __( 'Manage Settings', 'wpp' ),
          'manage_wpp_modules'         => __( 'Manage Features', 'wpp' ),

          //* WPP Taxonomies capability */
          'manage_wpp_categories'      => __( 'Manage Taxonomies', 'wpp' )
        );

        //* Adds Premium Feature Capabilities */
        $wpp_capabilities = apply_filters( 'wpp_capabilities', $wpp_capabilities );

        if( !is_object( $role ) ) {
          return;
        }

        foreach( (array) $wpp_capabilities as $cap => $value ) {
          if( empty( $role->capabilities[ $cap ] ) ) {
            $role->add_cap( $cap );
          }
        }
      }

      /**
       * Generates javascript file with localization.
       * Adds localization support to all WP-Property scripts.
       * Accessible via wp-ajax.php calls.
       *
       * @since 1.37.3.2
       * @author peshkov@UD
       */
      public function localize_scripts() {

        $l10n = array();

        //** Include the list of translations */
        include_once WPP_Path . 'l10n.php';

        /** All additional localizations must be added using the filter below. */
        $l10n = apply_filters( 'wpp::js::localization', $l10n );

        foreach( (array) $l10n as $key => $value ) {
          if( !is_scalar( $value ) ) {
            continue;
          }
          $l10n[ $key ] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
        }

        header( 'Content-type: application/x-javascript' );

        die( "var wpp = ( typeof wpp === 'object' ) ? wpp : {}; wpp.strings = " . json_encode( $l10n ) . ';' );

      }

      /**
       * WPP Contextual Help
       *
       * @global $current_screen
       *
       * @param  $args
       *
       * @author korotkov@ud
       */
      public function wpp_contextual_help( $args = array() ) {
        global $contextual_help;

        $args = Utility::parse_args( $args, array(
          'contextual_help' => array()
        ));

        //** If method exists add_help_tab in WP_Screen */
        if ( is_callable( array( 'WP_Screen', 'add_help_tab' ) ) ) {

          //** Loop through help items and build tabs */
          foreach ( (array) $args->contextual_help as $help_tab_title => $help ) {

            //** Add tab with current info */
            get_current_screen()->add_help_tab(
              array(
                'id' => sanitize_title( $help_tab_title ),
                'title' => __( $help_tab_title, 'wpp' ),
                'content' => implode( "\n", (array) $args->contextual_help[ $help_tab_title ] ),
              )
            );

          }

          //** Add help sidebar with More Links */
          get_current_screen()->set_help_sidebar(
            '<p><strong>' . __( 'For more information:', 'wpp' ) . '</strong></p>' .
            '<p>' . __( '<a href="https://usabilitydynamics.com/products/wp-property/" target="_blank">WP-Property Product Page</a>', 'wpp' ) . '</p>' .
            '<p>' . __( '<a href="https://usabilitydynamics.com/products/wp-property/forum/" target="_blank">WP-Property Forums</a>', 'wpp' ) . '</p>' .
            '<p>' . __( '<a href="https://usabilitydynamics.com/help/" target="_blank">WP-Property Tutorials</a>', 'wpp' ) . '</p>'
          );

        }

      }

      /**
       * Returns specific instance data which is used by javascript
       * Javascript Reference: window.wpp.instance
       *
       * @author peshkov@UD
       * @since 1.38
       * @return array
       */
      public function locale_instance() {
        global $wp_properties;

        $data = array(
          'request'        => $_REQUEST,
          'get'            => $_GET,
          'post'           => $_POST,
          'iframe_enabled' => false,
          'ajax_url'       => admin_url( 'admin-ajax.php' ),
          'home_url'       => home_url(),
          'user_logged_in' => is_user_logged_in() ? 'true' : 'false',
          'settings'       => $wp_properties,
        );

        if( isset( $data[ 'request' ][ 'wp_customize' ] ) && $data[ 'request' ][ 'wp_customize' ] == 'on' ) {
          $data[ 'iframe_enabled' ] = true;
        }

        return apply_filters( 'wpp::get_instance', $data );

      }

      /**
       * Get the WPP Singleton
       *
       * Concept based on the CodeIgniter get_instance() concept.
       *
       * @example
       *
       *      var settings = WPP::get_instance()->Settings;
       *      var api = WPP::$instance()->API;
       *
       * @static
       * @return object
       *
       * @method get_instance
       * @for WPP
       */
      public function &get_instance() {
        return self::$instance;
      }

    }

  }

}
