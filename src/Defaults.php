<?php

namespace WAR;

class Defaults {

	public $config;
	public $endpoints;
	public $models;

	public function __construct(){
		$this->config = $this->set_config();
		$this->endpoints = $this->set_endpoints();
		$this->models = $this->set_models();
	}

	public function set_config(){
		return [
			'api_name'					=> 'war',
			'api_prefix'				=> 'wp-json',
			'admin_toolbar'				=> false,
			'default_access'			=> false,
			'default_model_params'		=> [ 'id', 'created_on', 'updated_on', 'user' ],
			'enable_cors'				=> false,
			'filter_sideSearch_results'	=> false,
			'isolate_user_data'			=> true,
			'limit'						=> 10,
			'localize_war_object'		=> true
			'max_limit'					=> 100,
			'permalink'					=> '/posts/%postname%/',
			'sideLimit'					=> 10,
			'user_roles'				=> [],
			'url_id_param'				=> [ 'id', '\d+' ],
			'version'					=> 1,
			'war_jwt_expire'			=> ( time() + ( DAY_IN_SECONDS * 30 ) ),
		];
	}

	public function set_models(){
		return array();
	}

	public function set_endpoints(){
		return array();
	}

}
