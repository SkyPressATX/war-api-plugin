<?php

namespace WAR\Data;

use WAR\Helpers\GlobalHelpers AS Global_Helpers;

class QuerySearch {

	private $help;

	public function __construct(){
		$this->help = new Global_Helpers;
	}

	/**
	 * $filter syntax col:comp:val
	 **/
	public function parse_filters( $filters = array(), $model = '' ){
		if( empty( $filters ) ) return array();
		$filters = (array)$filters;
		array_walk( $filters, function( &$filter, $i, $model ){
			$filter = $this->build_filter_query( trim( $filter ), $model );
		}, $model );
		return $filters;
	}

	public function parse_filters_with_model( $filters = array(), $prefix = '' ){
		if( empty( $filters ) ) return array();
		$result = array();

		foreach( $filters as $i => $filter ){
			$f_explode = explode( ':', $filter );

			if( sizeof( $f_explode ) > 3 || ( end( $f_explode ) == "null" || end( $f_explode ) == "nnull" ) ){
				$model = $f_explode[0];
				array_splice( $f_explode, 0, 1);

				$f = implode( ':', $f_explode );

				if( ! isset( $result[ $model ] ) ) $result[ $model ] = array();
				$table = $prefix . $model;
				$result[ $model ][] = $this->build_filter_query( $f, $model );
			}
		};

		return $result;
	}

	public function parse_sql_types( $params = array(), $param_map = array() ){
		if( empty( $params ) || empty( $param_map ) ) return array();
		$this->param_map = $param_map;
		array_walk( $params, function( &$param ){
			$type = ( isset( $this->param_map[ $param ] ) ) ? $this->param_map[ $param ] : $param;
			$param = '`' . $param .'` ' . $this->help->sql_data_type( $type );
		});

		return $params;
	}

	public function parse_primary_key( $params = array() ){
		if( empty( $params ) ) return array();
		$primary_keys = array_keys( array_filter( $params, function( $param ){
			return ( is_array( $param ) && isset( $param[ 'unique' ] ) && $param[ 'unique' ] );
		}) );
		array_walk( $primary_keys, function( &$key ){
			$key = '`' . $key . '`';
		});
		return $primary_keys;
	}
	/**
	 * $group Array( $string, $string, $string )
	 **/
	public function parse_group( $group = array(), $model = '' ){
		if( empty( $group ) ) return $group;
		// sanitize $group values
		array_walk( $group, function( &$g, $i, $model ){
			$g = $model . '.' . (string)preg_replace( '/[^a-z0-9_]/', '', trim( $g ) );
		}, $model );
		return $group;
	}

	/**
	 * $order syntax col:direction
	 **/
	public function parse_order( $orders = array(), $model = '' ){
		if( empty( $orders ) ) return $orders;
		if( ! is_array( $orders ) && is_string( $orders ) ) $orders = [ $orders ];
		array_walk( $orders, function( &$order, $i, $model ){
			$order = $this->build_order_query( trim( $order ), $model );
		}, $model );

		return implode( ', ', $orders );
	}

	/**
	 * $limit Integer Default 10
	 **/
	public function parse_limit( $limit = 10 ){
		return absint( trim( $limit ) );
	}

	/**
	 * $page Integer
	 * $offset Integer ( $page * $limit )
	 **/
	public function parse_page( $page = 1, $limit = 10 ){
		return absint( ( ( trim( $page ) - 1 ) * trim( $limit  ) ) );
	}

	public function parse_join( $assoc = array(), $model = '', $base_model = '' ){
		if( empty( $assoc ) ) return false;
		$a = (object)$assoc;

		$join = [
			'match' => $base_model . '.' . $a->match,
		];

		return $join;


		if( $a->assoc == 'one' )
			$join_on = $model . '.' . $a->bind . ' = ' . $base_model . '.' . $a->match;
		else
			$join_on = 'FIND_IN_SET( ' . $base_model . '.' . $a->bind . ',' . $model . '.' . $a->match . ' )';

		if( isset( $join_on ) ){
			$join = [ 'as' => $model, 'on' => $join_on ];
			return $join;
		}

		return false;
	}

	public function build_filter_query( $filter, $model ){
		$san_regex = '/[^a-z0-9_\.@]/';
		$f = explode( ':', strtolower( $filter ) );
		array_walk( $f, function( &$x ){ $x = (string) esc_sql( trim( $x ) ); });

		switch( $f[1] ){
			case 'like':
				return $model . '.' . $f[0] . ' LIKE "%' . preg_replace( $san_regex, '', $f[2] ) . '%"';
				break;
			case 'nlike':
				return $model . '.' . $f[0] . ' NOT LIKE "%' . preg_replace( $san_regex, '', $f[2] ) . '%"';
				break;
			case 'gt':
				return $model . '.' . $f[0] . ' > ' . $this->help->quote_it( $f[2] );
				break;
			case 'lt':
				return $model . '.' . $f[0] . ' < ' . $this->help->quote_it( $f[2] );
				break;
			case 'gte':
				return $model . '.' . $f[0] . ' >= ' . $this->help->quote_it( $f[2] );
				break;
			case 'lte':
				return $model . '.' . $f[0] . ' <= ' . $this->help->quote_it( $f[2] );
				break;
			case 'eq':
				return $model . '.' . $f[0] . ' = ' . $this->help->quote_it( $f[2] );
				break;
			case 'neq':
				return $model . '.' . $f[0] . ' != ' . $this->help->quote_it( $f[2] );
				break;
			case 'in':
				$la = explode( '|', $f[2] );
				array_walk( $la, function( &$l ){ $l = $this->help->quote_it( $l ); });
				return $model . '.' . $f[0] . ' IN ( ' . implode( ',', $la ) . ' )';
				break;
				break;
			case 'nin':
				$la = explode( '|', $f[2] );
				array_walk( $la, function( &$l ){ $l = $this->help->quote_it( $l ); });
				return $model . '.' . $f[0] . ' NOT IN ( ' . implode( ',', $la ) . ' )';
				break;
			case 'null':
				return $model . '.' . $f[0] . ' IS NULL';
				break;
			case 'nnull':
				return $model . '.' . $f[0] . ' IS NOT NULL';
				break;
		}
	}

	public function build_order_query( $order, $model = '' ){
		$o = explode( ':', strtolower( $order ) );
		// $order_by = esc_sql( $model . '.' . trim( $o[0] ) );
		$order_by = '`' . esc_sql( trim( $o[0] ) ) . '`';
		if( sizeof( $o ) > 1 ){
			$d = strtoupper( trim( $o[1] ) );
			if( in_array( $d, [ 'ASC', 'DESC' ] ) ) $direction = $d;
		}
		return ( isset( $direction ) ) ? $order_by . ' ' . $direction : $order_by;
	}

}
