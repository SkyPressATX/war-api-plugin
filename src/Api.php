<?php
namespace WAR;

use WAR\Init as Init;
use WAR\Defaults as Defaults;

class Api {

	private $config;
	private $endpoints;
	private $models;
	public function __construct(){
		$war_default = new Defaults;
		$this->config = $war_default->config;
		$this->endpoints = $war_default->endpoints;
		$this->models = $war_default->models;
	}
	public function add_config( $slug = false, $val = false ){
		if( is_array( $slug ) )
			$this->config = array_merge( $this->config, $slug );
		else
			$this->config[ $slug ] = $val;
	}
	public function add_endpoints( $slug = false, $endpoint = array() ){
		if( is_array( $slug ) )
			$this->endpoints = array_merge( $this->endpoints, $slug );
		else
			$this->endpoints[ $slug ] = $val;
	}
	public function add_models( $slug = false, $model = array() ){
		if( is_array( $slug ) )
			$this->models = array_merge( $this->models, $slug );
		else
			$this->models[ $slug ] = $val;
	}
	public function init(){
		define( 'WAR_API_INIT', true );
		$this->config[ 'nonce' ] = wp_create_nonce('wp_rest'); //Add this here so that it can't be edited
		$war_init = new Init( $this->config, $this->endpoints, $this->models );
		$war_init->init();
	}
}
