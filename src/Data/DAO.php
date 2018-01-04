<?php

namespace WAR\Data;

use WAR\Data\QuerySearch		as Query_Search;
use WAR\Data\QuerySelect		as Query_Select;
use WAR\Helpers\GlobalHelpers	as Global_Helpers;
use WAR\Data\QueryAssoc			as Query_Assoc;
use WAR\Data\QueryBuilder		as Query_Builder;
use WAR\Data\WarDB				as War_DB;

class DAO {

	/**
	 * Start with Other Classes
	 **/
	private $query_search;
	private $query_select;
	private $query_builder;
	private $query_assoc;
	private $help;
	private $war_db;

	private $request;
	private $model;
	private $params;
	private $war_config;
	private $table_prefix;
	private $query_map;
	private $url_id_param;
	private $default_model_params;

	public function __construct( $db_info = array(), $model = array(), $request = array(), $war_config = array() ){
		try {
			$this->war_db = War_DB::init( $db_info );
		}catch( \Exception $e ){
			throw new \Exception( $e->getMessage() );
		}

		$this->model = (object)$model;
		$this->request = $request;
		$this->params = $this->request->params;
		$this->war_config = $war_config;
		$this->isolate = $this->determine_isolation();
		$this->table_prefix = $this->get_table_prefix( $db_info );
		$this->table = esc_sql( $this->table_prefix . $this->model->name );
		$this->url_id_param = $this->get_url_id_param();
		$this->default_model_params = $this->get_default_model_params();

		$this->query_search = new Query_Search;
		$this->query_select = new Query_Select;
		$this->help = new Global_Helpers;
		if( property_exists( $this->model, 'assoc' ) )
			$this->query_assoc = new Query_Assoc( $this->model->assoc, $this->params, $this->table_prefix );

	}

	/**
	 * Read All Items from DB
	 *
	 * Get Items, Associated Items, and Info Results
	 *
	 * @return Object | Data, Info, Pages
	 *
	 **/
	public function read_all(){
		try {
			$this->add_current_user_to_filter();
			$this->create_table();
			$this->query_map = [];

			if( property_exists( $this->model, 'assoc' ) && ! empty( $this->model->assoc ) )
				$this->query_map[ 'assoc' ] = $this->query_assoc->get_query_maps();

			//Build war_db select_all() params
			$this->query_map[ 'query' ] = [
				// 'select' => ( property_exists( $this->params, 'select' ) ) ? $this->params->select : [],
				'select' => $this->query_select->parse_select( $this->params->select, $this->table ),
				'table'  => $this->table,
				'where'  => $this->query_search->parse_filters( $this->params->filter, $this->table ),
				'limit'  => $this->query_search->parse_limit( $this->params->limit ),
				// 'order'  => $this->query_search->parse_order( $this->params->order, $table ),
				'offset' => $this->query_search->parse_page( $this->params->page, $this->params->limit )
			];

			/** Use Grouping if appropriate **/
			if( property_exists( $this->params, 'group' ) && property_exists( $this->params, 'select' ) )
				$this->query_map[ 'query' ][ 'group' ] = $this->query_search->parse_group( $this->params->group, $this->table );

			/** Use Order By if appropriate **/
			if( property_exists( $this->params, 'order' ) )
				$this->query_map[ 'query' ][ 'order' ] = $this->query_search->parse_order( $this->params->order, $this->table );

			// Lets look for any assoc queries that have a where statement. Pull that data first
			if( isset( $this->query_map[ 'assoc' ] ) ){
				foreach( $this->query_map[ 'assoc' ] as $model => &$assoc ){
					if( ! empty( $assoc[ 'query' ][ 'where' ] ) ){
						$assoc_where = $this->query_assoc->get_side_search_filter( $assoc, $model, $this->table );
						if( $assoc_where ){
							if( ! empty( $assoc_where[ 'data' ] ) ) $assoc[ 'data' ] = $assoc_where[ 'data' ];
							$this->query_map[ 'query' ][ 'where' ][] = $assoc_where[ 'filter' ];

						}
					}
				}
			}

			$total = $this->query_map[ 'query' ];
			$total[ 'select' ] = 'COUNT(' . $this->table . '.' . $this->url_id_param . ')';
			unset( $total[ 'limit' ] );
			unset( $total[ 'offset' ] );
			unset( $total[ 'group' ] );

			$data = $this->war_db->select_all( $this->query_map[ 'query' ] );

			//Append our Associated Data
			if( property_exists( $this->params, 'sideSearch' ) || $this->params->sideLoad !== false && ! empty( $this->query_map[ 'assoc' ] ) )
				$data = $this->query_assoc->append_assoc_data( $this->query_map[ 'assoc' ], $data );

			$result = [ 'data' => $data ];
			if( $this->params->_info ){
				$result[ '_info' ]	= [ 'total' => $this->war_db->select_one( $total ) ];
				$result[ '_pages' ]	= [ 'current_page' => ( property_exists( $this->params, 'page' ) ) ? $this->params->page : NULL ];
			}
			if( $this->params->_map ){
				$result[ '_map' ] = $this->model;
				unset( $result[ '_map' ]->callback );
				if( isset( $result[ '_map' ]->db_info ) ) unset( $result[ '_map' ]->db_info );
				$result[ '_map' ]->url_id_param = ( isset( $result[ '_map']->url_id_param ) ) ? $result[ '_map' ]->url_id_param[0] : $this->war_config->url_id_param[0];
				if( isset( $result[ '_map' ]->params ) )
					array_walk( $result[ '_map' ]->params, function( &$p, $k ){
						$p[ 'name' ] = $k;
						unset( $p['sanitize_callback'] );
						unset( $p['validate_callback'] );
					});
			}

			if( $result[ '_info' ][ 'total' ] > 0 ){
				$result[ '_info' ][ 'count' ]  = ( $result[ '_info' ][ 'total' ]  < $this->params->limit ) ? $result[ '_info' ][ 'total' ]  : $this->params->limit;
				$result[ '_pages' ][ 'total_pages' ]  = ( (integer)ceil( $result[ '_info' ][ 'total' ] / $this->params->limit ) );
				$result[ '_pages' ][ 'next_page' ] = ( ($this->params->page + 1) <= $result[ '_pages' ][ 'total_pages' ]  ) ? ($this->params->page + 1) : $result[ '_pages' ][ 'total_pages' ];
			}
			return (object)$result;

		} catch( \Exception $e ){
			throw new \Exception( $e->getMessage() );
		}

	} // END Read Items Method

	public function read_one(){
		try {
			$id = $this->url_id_param;
			$this->params->_info = false;
			$this->params->filter = [ $id . ':eq:' . $this->params->$id ];
			$this->params->limit = 1;
			$this->params->page = 1;
			unset( $this->params->$id );

			$item = $this->read_all();

			return $item[0];
		} catch( \Exception $e ){
			throw new \Exception( $e->getMessage() );
		}
	}

	public function insert_one(){
		try {
			$this->unset_empty_values();
			$this->create_table();
			$this->adjust_table_columns();

			if( property_exists( $this->request, 'current_user' ) && ! empty( $this->request->current_user ) && in_array( 'user', $this->default_model_params ) )
				$this->params->user = $this->request->current_user->id;

			// Implode any arrays in the params
			array_walk( $this->params, function( &$p ){
				if( is_array( $p ) ) $p = implode( ',', $p );
			});

			$insert_map = [
				'table' => $this->table,
				'data'  => $this->params
			];

			return $this->war_db->insert( $insert_map );
		} catch( \Exception $e ){
			throw new \Exception( $e->getMessage() );
		}
	}

	public function update_one(){
		try {
			$this->unset_empty_values();
			$this->add_current_user_to_filter();
			$this->adjust_table_columns();

			$id = $this->url_id_param;

			if( ! property_exists( $this->params, 'filter' ) ) $this->params->filter = [];
			$this->params->filter[] = $id .':eq:' . $this->params->$id;

			$data = (object)(array)$this->params;
			if( property_exists( $data, $id ) ) unset( $data->$id );
			if( property_exists( $data, 'filter' ) ) unset( $data->filter );

			$update_map = [
				'table' => $this->table,
				'data'  => $data,
				'where' => $this->query_search->parse_filters( $this->params->filter, $this->table )
			];

			return $this->war_db->update( $update_map );
		} catch( \Exception $e ){
			throw new \Exception( $e->getMessage() );
		}
	}

	public function delete_one(){
		try {
			$this->add_current_user_to_filter();
			$id = $this->url_id_param;

			if( ! property_exists( $this->params, 'filter' ) ) $this->params->filter = [];
			$this->params->filter[] = $id . ':eq:' . $this->params->$id;

			$delete_map = [
				'table' => $this->table,
				'where' => $this->query_search->parse_filters( $this->params->filter, $this->table )
			];

			return $this->war_db->delete( $delete_map );
		} catch( \Exception $e ){
			throw new \Exception( $e->getMessage() );
		}
	}

	private function create_table(){
		$create_map = [
			'table'   => $this->table,
			'data'    => $this->query_search->parse_sql_types( array_keys( $this->model->params ), $this->model->params ),
			'primary' => $this->query_search->parse_primary_key( $this->model->params )
		];

		if( in_array( 'id', $this->default_model_params ) ) $create_map[ 'keys' ] = ['`id`'];

		if( property_exists( $this->model, 'assoc' ) ){
			foreach( $this->model->assoc as $model => $map ){
				if( isset( $map[ 'bind' ] ) && ! in_array( '`' . $map[ 'bind' ] . '`', $create_map[ 'keys' ] ) && ! in_array( '`' . $map[ 'bind' ] . '`', $create_map[ 'primary' ] ) )
					$create_map[ 'keys' ][] = '`' . $map[ 'bind' ] . '`';
			}
		}

		$create_map[ 'data' ] = array_merge( $this->default_table_columns(), $create_map[ 'data' ] );

		return $this->war_db->create_table( $create_map );
	}

	private function adjust_table_columns(){
		//Get our columns to compare against
		$model_col   = array_merge( $this->default_model_params, array_keys( (array)$this->model->params ) );
		$current_col_map = [
			'select' => '`COLUMN_NAME`',
			'table'  => 'INFORMATION_SCHEMA.COLUMNS',
			'where'  => [ '`TABLE_NAME` = "' . $this->table . '"' ]
		];
		$current_col = array_column( $this->war_db->select_query( $current_col_map ), 'COLUMN_NAME' );

		$remove_columns = array_values( array_diff( $current_col, $model_col ) );
		$add_columns    = array_values( array_diff( $model_col, $current_col ) );

		if( ! empty( $remove_columns ) ){
			array_walk( $remove_columns, function( &$col ){
				$col = 'DROP `' . esc_sql( $col ) . '`';
			});
			$remove_col_map = [
				'table' => $this->table,
				'data'  => $remove_columns
			];
			$remove_call = $this->war_db->alter_table( $remove_col_map );
		}

		if( ! empty( $add_columns ) ){
			$defaults_to_add = array_filter( $add_columns, function( $p ){
				return in_array( $p, $this->default_model_params );
			});
			$params_to_add = array_values( array_diff( $add_columns, $defaults_to_add ) );
			$add_columns = array_merge( $this->query_search->parse_sql_types( $params_to_add, $this->model->params ), $this->default_table_columns( $defaults_to_add ) );
			array_walk( $add_columns, function( &$col ){
				$col = 'ADD ' . $col;
			});
			$add_col_map = [
				'table' => $this->table,
				'data'  => $add_columns
			];
			$add_call = $this->war_db->alter_table( $add_col_map );
		}

		return true;
	}

	private function unset_empty_values(){
		$params = (array)$this->params;
		array_walk( $params, function( &$p, $k ){
			if( is_bool( $p ) && $p === false ) $p = (int)0;
			if( is_string( $p ) && empty( $p ) ) unset( $this->params->$k );
		});
		$this->params = (object)$params;
	}

	private function determine_isolation(){
		$isolate = $this->war_config->isolate_user_data;
		if( property_exists( $this->model, 'isolate_user_data' ) ) $isolate = $this->model->isolate_user_data;
		return $isolate;
	}

	private function add_current_user_to_filter(){
		if( ! $this->isolate ) return;

		if( empty( $this->params ) ) $this->params = [ 'filter' => [] ];
		$this->params->filter[] =  'user:eq:' . $this->request->current_user->id;
	}

	private function get_table_prefix( $db_info = array() ){
		//Use $this->model->table_prefix if set
		if( property_exists( $this->model, 'table_prefix' ) ) return $this->model->table_prefix;
		//Use $db_info[ 'table_prefix' ] if set
		if( ! empty( $db_info ) && isset( $db_info[ 'table_prefix' ] ) ) return $db_info[ 'table_prefix' ];
		//Use $this->war_config->table_prefix if set
		if( property_exists( $this->war_config, 'table_prefix' ) ) return $this->war_config->table_prefix;
		//Use WordPress $table_prefix as a last resort
		global $table_prefix;
		//See if we should append the api_name to our table for easier navigation
		if( property_exists( $this->war_config, 'api_name' ) && $this->war_config->api_name != 'wp-json' )
			return $table_prefix . $this->war_config->api_name . '_';
		//Return what is set in wp-confg.php
		return $table_prefix;
	}

	private function get_url_id_param(){
		$url_id_param = ( property_exists( $this->model, 'url_id_param' ) ) ? $this->model->url_id_param : $this->war_config->url_id_param;
		if( ! is_array( $url_id_param ) || sizeof( $url_id_param ) !== 2 ) throw new \Exception( 'URL ID Param not properly configured' );
		return $url_id_param[0];
	}

	/**
	 * dao_default_create_values
	 *
	 * @return Array
	 */
	private function default_table_columns( $default_params = [] ){
		if( empty( $default_params ) ) $default_params = $this->default_model_params;
		$defaults_parsed = [];
		foreach( $default_params as $param ){
			if( $param === 'id' )			$defaults_parsed[] = '`id` MEDIUMINT NOT NULL AUTO_INCREMENT';
			if( $param === 'created_on' )	$defaults_parsed[] = '`created_on` datetime DEFAULT CURRENT_TIMESTAMP';
			if( $param === 'updated_on' )	$defaults_parsed[] = '`updated_on` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
			if( $param === 'user' )			$defaults_parsed[] = '`user` MEDIUMINT NOT NULL';
		}
		return $defaults_parsed;
	}

	/**
	 * Parse either Model Map or WAR Config
	 *
	 * @return Array[ string ]
	 **/
	private function get_default_model_params(){
		if( isset( $this->model->default_params ) ) return $this->model->default_params;
		return $this->war_config->default_model_params;
	}

}
