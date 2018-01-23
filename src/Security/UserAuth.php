<?php

namespace WAR\Security;

use WAR\Security\JWT as War_JWT;

class UserAuth {

	private $user_id;
	private $auth_type;
	private $authed = null;

	public function __construct(){
		$this->get_user_id_by_jwt();
		if( empty( $this->user_id ) ) $this->get_user_id_by_nonce(); //Try the nonce then
		$this->auth_used( $this->authed );
	}


	public function get_user_id(){
		return ( property_exists( $this, 'user_id' ) && $this->user_id !== 0 ) ? $this->user_id : false;
	}

	public function get_auth_type(){
		return ( property_exists( $this, 'auth_type' ) ) ? $this->auth_type : NULL;
	}

	private function get_user_id_by_jwt(){
		if( isset( $this->user_id ) ) return;
		$key_to_check( $this->get_header_value( 'Authorization' ) );
		if( NULL !== $key_to_check ) $this->user_id = $this->key_check( $key_to_check );
	}

	private function get_user_id_by_nonce(){
		if( isset( $this->user_id ) ) return;
		$key_to_check( $this->get_header_value( 'X-WP-Nonce' ) );
		if( NULL !== $key_to_check ) $this->user_id = $this->nonce_check( $key_to_check );
	}

	private function key_check( $key ){
		if(empty($key)) return false;
		$jwt = new War_JWT;
		$res = $jwt->jwt_key_decode( $key );
		if( is_wp_error( $res ) ){
			$this->authed = $res;
			return;
		}
		$this->auth_type = 'JWT';
		$this->authed = true;
		return $res;
	}

	private function nonce_check( $nonce ){
		if( empty( $nonce ) ) return false;

		$verify = wp_verify_nonce( $nonce, 'wp_rest' );

		if( ! $verify ){
			$this->authed = false;
			return;
		}

		$this->auth_type = 'COOKIE';
		$this->authed = true;
		return get_current_user_id();
	}

	/**
	 * Get value of a header
	 *
	 * @since 0.1.2-alpha
	 *
	 * @param string $header_key Header to get value for
	 * @return string | NULL Value of header, unprocessed
	 */
	private function get_header_value( $header_key = NULL ) {
		// Return NULL if no header key is provided
		if( NULL === $header_key ) return $header_key;
		// Use $_SERVER if getallheaders is not a function
		if( ! function_exists( 'getallheaders' ) ) {
			$header = 'HTTP_' . strtoupper( str_replace( '-', '_', $header_key ) );
			return ( array_key_exists( $header, $_SERVER ) ) ? $_SERVER[ $header ] : NULL;
		}

		// Use getallheaders function
		$headers = getallheaders();
		return ( array_key_exists( $header_key, $headers ) ) ? $headers[ $header_key ] : NULL;
	}

	private function auth_used(){
		add_filter( 'rest_authentication_errors', function( $authed ){
			return $this->authed;
		});
	}


}
