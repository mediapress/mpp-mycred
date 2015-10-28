<?php
/**
 * Plugin Name: MediaPress - myCRED Addon
 * Plugin URI: http://buddydev.com/plugins/mpp-mycred/
 * Version: 1.0.0
 * Author: BuddyDev Team
 * Author URI: http://buddydev.com
 * Description: Give points to users for their media upload using myCRED
 * License: GPL
 * 
 */

/**
 * This class sets up the myCRED type and  
 * loads the myCRED action helper
 * 
 */
class MPP_myCRED_Helper {
	/**
	 * Singleton Instance
	 * 
	 * @var MPP_myCRED_Helper 
	 */
	private static $instance = null;
	
	private function __construct () {
		
		$this->setup_hooks();
	}
	/**
	 * Get the singleton instance
	 * 
	 * @return MPP_myCRED_Helper
	 */
	public static function get_instance() {
		
		if( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		
		return self::$instance;
		
	}
	/**
	 * Setup hooks 
	 */
	private function setup_hooks() {
		
		add_action( 'mpp_loaded', array( $this, 'load' ) );
		add_filter( 'mycred_setup_hooks', array( $this, 'setup_mycred_type' ) );
		
		$this->load_textdomain();
		
	}
	
	/**
	 * Load required files
	 * 
	 */
	public function load() {

		$path = plugin_dir_path( __FILE__ );

		if( class_exists( 'myCRED_Hook' ) ) {	
			require_once  $path . 'core/actions.php';
		}
	}

	private function load_textdomain() {
		
		load_plugin_textdomain( 'mpp-mycred', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
		
	}
	
	public function setup_mycred_type( $installed ) {

		$installed['mediapress'] = array(
			'title'       => __( 'MediaPress', 'mycred' ),
			'description' => __( 'Award / Deduct %_plural% for users creating galleries or uploading new media.', 'mpp-mycred' ),
			'callback'    => array( 'MPP_myCRED_Actions_Helper' )
		);
		
		return $installed;
	}
	
	
}

MPP_myCRED_Helper::get_instance();