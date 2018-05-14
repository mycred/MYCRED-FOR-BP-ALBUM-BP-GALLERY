<?php
/**
 * Plugin Name: myCRED for BP Album+ or BP Gallery
 * Plugin URI: http://mycred.me
 * Description: Allows you to reward users for creating new galleries.
 * Version: 1.0
 * Tags: mycred, points, buddypress, album, gallery
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: support@mycred.me
 * Requires at least: WP 4.0
 * Tested up to: WP 4.6
 * Text Domain: mycred_gallery
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
if ( ! class_exists( 'myCRED_BP_Gallery' ) ) :
	final class myCRED_BP_Gallery {

		// Plugin Version
		public $version             = '1.0';

		// Instnace
		protected static $_instance = NULL;

		// Current session
		public $session             = NULL;

		public $slug                = '';
		public $domain              = '';
		public $plugin              = NULL;
		public $plugin_name         = '';
		protected $update_url       = 'http://mycred.me/api/plugins/';

		/**
		 * Setup Instance
		 * @since 1.0
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Define
		 * @since 1.0
		 * @version 1.0
		 */
		private function define( $name, $value, $definable = true ) {
			if ( ! defined( $name ) )
				define( $name, $value );
		}

		/**
		 * Require File
		 * @since 1.0
		 * @version 1.0
		 */
		public function file( $required_file ) {
			if ( file_exists( $required_file ) )
				require_once $required_file;
		}

		/**
		 * Construct
		 * @since 1.0
		 * @version 1.0
		 */
		public function __construct() {

			$this->slug        = 'mycred-buddypress-gallery';
			$this->plugin      = plugin_basename( __FILE__ );
			$this->domain      = 'mycred_gallery';
			$this->plugin_name = 'myCRED for BP Album+ or BP Gallery';

			$this->define_constants();
			$this->plugin_updates();

			add_filter( 'mycred_setup_hooks',    array( $this, 'register_hook' ) );
			add_action( 'mycred_init',           array( $this, 'load_textdomain' ) );
			add_action( 'mycred_all_references', array( $this, 'add_badge_support' ) );
			add_action( 'mycred_load_hooks',     'mycred_load_buddypress_gallery_hook' );

		}

		/**
		 * Define Constants
		 * @since 1.0
		 * @version 1.0
		 */
		public function define_constants() {

			$this->define( 'MYCRED_BP_GALLERY_SLUG',  $this->slug );
			$this->define( 'MYCRED_DEFAULT_TYPE_KEY', 'mycred_default' );

		}

		/**
		 * Includes
		 * @since 1.0
		 * @version 1.0
		 */
		public function includes() { }

		/**
		 * Load Textdomain
		 * @since 1.0
		 * @version 1.0
		 */
		public function load_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );

			load_textdomain( $this->domain, WP_LANG_DIR . '/' . $this->slug . '/' . $this->domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( $this->domain, false, dirname( $this->plugin ) . '/lang/' );

		}

		/**
		 * Plugin Updates
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_updates() {

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ), 300 );
			add_filter( 'plugins_api',                           array( $this, 'plugin_api_call' ), 300, 3 );
			add_filter( 'plugin_row_meta',                       array( $this, 'plugin_view_info' ), 300, 3 );

		}

		/**
		 * Register Hook
		 * @since 1.0
		 * @version 1.0
		 */
		public function register_hook( $installed ) {

			if ( ! function_exists( 'bpa_init' ) && ! function_exists( 'bpgpls_init' ) ) return $installed;

			$installed['hook_bp_gallery'] = array(
				'title'       => __( 'BuddyPress: Gallery Actions', $this->domain ),
				'description' => __( 'Awards %_plural% for creating a new gallery either using BP Album+ or BP Gallery.', $this->domain ),
				'callback'    => array( 'myCRED_BuddyPress_Gallery' )
			);

			return $installed;

		}

		/**
		 * Add Badge Support
		 * @since 1.0
		 * @version 1.0
		 */
		public function add_badge_support( $references ) {

			if ( ! function_exists( 'bpa_init' ) && ! function_exists( 'bpgpls_init' ) ) return $references;

			$references['new_gallery'] = __( 'New Gallery (BP Gallery)', $this->domain );

			return $references;

		}

		/**
		 * Plugin Update Check
		 * @since 1.0
		 * @version 1.0
		 */
		public function check_for_plugin_update( $checked_data ) {

			global $wp_version;

			if ( empty( $checked_data->checked ) )
				return $checked_data;

			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'version', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			// Start checking for an update
			$response = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $response ) ) {

				$result = maybe_unserialize( $response['body'] );

				if ( is_object( $result ) && ! empty( $result ) )
					$checked_data->response[ $this->slug . '/' . $this->slug . '.php' ] = $result;

			}

			return $checked_data;

		}

		/**
		 * Plugin View Info
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_view_info( $plugin_meta, $file, $plugin_data ) {

			if ( $file != $this->plugin ) return $plugin_meta;

			$plugin_meta[] = sprintf( '<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->slug .
				'&TB_iframe=true&width=600&height=550' ) ),
				esc_attr( __( 'More information about this plugin', $this->domain ) ),
				esc_attr( $this->plugin_name ),
				__( 'View details', $this->domain )
			);

			return $plugin_meta;

		}

		/**
		 * Plugin New Version Update
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_api_call( $result, $action, $args ) {

			global $wp_version;

			if ( ! isset( $args->slug ) || ( $args->slug != $this->slug ) )
				return $result;

			// Get the current version
			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'info', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			$request = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $request ) )
				$result = maybe_unserialize( $request['body'] );

			return $result;

		}

	}
endif;

function mycred_bp_gallery_plugin() {
	return myCRED_BP_Gallery::instance();
}
mycred_bp_gallery_plugin();

/**
 * BuddyPress Gallery Hook
 * @since 1.0
 * @version 1.0
 */
if ( ! function_exists( 'mycred_load_buddypress_gallery_hook' ) ) :
	function mycred_load_buddypress_gallery_hook() {

		if ( class_exists( 'myCRED_BuddyPress_Gallery' ) || ( ! function_exists( 'bpa_init' ) && ! function_exists( 'bpgpls_init' ) ) ) return;

		class myCRED_BuddyPress_Gallery extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {

				parent::__construct( array(
					'id'       => 'hook_bp_gallery',
					'defaults' => array(
						'new_gallery' => array(
							'creds'       => 1,
							'log'         => '%plural% for new gallery',
							'limit'       => '0/x'
						)
					)
				), $hook_prefs, $type );

			}

			/**
			 * Run
			 * @since 1.0
			 * @version 1.0
			 */
			public function run() {

				if ( $this->prefs['new_gallery']['creds'] != 0 ) {
					add_action( 'bp_gallplus_data_after_save', array( $this, 'new_gallery' ) );
					add_action( 'bp_album_data_after_save',    array( $this, 'new_gallery' ) );
				}

			}

			/**
			 * New Gallery
			 * @since 1.0
			 * @version 1.0
			 */
			public function new_gallery( $gallery ) {

				// Check if user is excluded
				if ( $this->core->exclude_user( $gallery->owner_id ) ) return;

				// Make sure this is unique event
				if ( ! $this->over_hook_limit( 'new_gallery', 'new_buddypress_gallery', $gallery->owner_id ) )
					$this->core->add_creds(
						'new_buddypress_gallery',
						$gallery->owner_id,
						$this->prefs['new_gallery']['creds'],
						$this->prefs['new_gallery']['log'],
						$gallery->id,
						'bp_gallery',
						$this->mycred_type
					);

			}

			/**
			 * Settings
			 * @since 1.0
			 * @version 1.0
			 */
			public function preferences() {

				$prefs = $this->prefs;

?>
<!-- Creds for New Gallery -->
<label for="<?php echo $this->field_id( array( 'new_gallery', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Gallery', 'mycred_gallery' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_gallery', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_gallery', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_gallery']['creds'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_gallery', 'limit' ) ); ?>"><?php _e( 'Limit', 'mycred_gallery' ); ?></label>
		<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_gallery', 'limit' ) ), $this->field_id( array( 'new_gallery', 'limit' ) ), $prefs['new_gallery']['limit'] ); ?>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_gallery', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred_gallery' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_gallery', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_gallery', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['new_gallery']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<?php
			}

			/**
			 * Sanitise Preferences
			 * @since 1.0
			 * @version 1.0
			 */
			function sanitise_preferences( $data ) {

				if ( isset( $data['new_gallery']['limit'] ) && isset( $data['new_gallery']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['new_gallery']['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['new_gallery']['limit'] = $limit . '/' . $data['new_gallery']['limit_by'];
					unset( $data['new_gallery']['limit_by'] );
				}

				return $data;

			}

		}

	}
endif;
