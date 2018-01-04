<?php

namespace WAR;

use WAR\Security\User			as War_User;
use WAR\Security\UserRoles		as War_User_Roles;

/**
 * These methods should run, well, automatically; at the right time
 **/
class AutoConfig {

	private $war_config;

	public function __construct( $config = array() ){
		$this->war_config = $config;
	}

	public function manage_admin_toolbar(){
		if( ! is_bool( $this->war_config->admin_toolbar ) ) $this->war_config->admin_toolbar = false;
        show_admin_bar( $this->war_config->admin_toolbar );
	}

	public function set_user_roles(){
		if( ! property_exists( $this->war_config, 'user_roles' ) ) return;
		$user_roles = new War_User_Roles;
		$user_roles->update_roles( $this->war_config->user_roles );
	}

	public function add_war_object( $war_object ){
		$wu = new War_User;

		$war_object = array(
			'warPath' => get_template_directory_uri(),
			'childPath' => get_stylesheet_directory_uri(),
			'authHeader' => 'X-WP-Nonce',
			'authKey' => $this->war_config->nonce,
			'permalink' => preg_replace('/\%.+\%/',':slug', get_option( 'permalink_structure' ) ),
			'category_base' => preg_replace('/\%.+\%/',':slug', get_option( 'category_base' ) ),
			'apiRoot' => rest_get_url_prefix(),
			'apiNamespace' => $this->war_config->namespace,
			'user' => $wu->get_war_user(),
			'root' => esc_url_raw( rest_url() )
		);

		return $war_object;
	}

	public function war_localize(){
		wp_register_script('war_site_details', null);

        $war_object = apply_filters( 'war_object', [] );
        wp_localize_script('war_site_details','warObject',$war_object);
        wp_enqueue_script('war_site_details');
	}

	public function set_api_prefix( $prefix ){
        return $this->war_config->api_prefix;
    }

}
