<?php

namespace WAR\Security;

class RoleCheck {

	private $required_role;
	private $current_user;

	public function __construct( $role = false, $current_user = array() ){
		$this->required_role = $role;
		$this->current_user = $current_user;
	}

	public function has_access(){
		if( $this->required_role === 'null' ) return true; //Open access for all

		if( $this->current_user === NULL ) return false; //Need to have some sort of auth, even just a nonce by now
		if( ! property_exists( $this->current_user, 'auth' ) ) return false; //Need to have some sort of auth, even just a nonce by now

		if( $this->required_role === false && $this->current_user->auth === 'COOKIE' ) return true; //Access only via the Front End

		if( ! property_exists( $this->current_user, 'id' ) ) return false; //We need a really real user by this point

		if( ! $this->current_user || $this->current_user->id === (int)0 ) return false; //Should be logged in at this point

		if( $this->required_role === true ) return true; // If logged in, then give access

		if( current_user_can( 'create_users' ) ) return true; //Adminstrator

		return ( in_array( $this->required_role, $this->current_user->caps ) );
	}

} // END Role_Check
