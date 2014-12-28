<?php
	
	/*
	Plugin Name: Gravity Forms Data Populaiton
	Plugin URI: http://travislop.es
	Description: Quickly test your forms with one click data population
	Version: 1.0.0
	Author: travislopes
	Author URI: http://travislop.es
	*/
		
	define( 'GF_DATAPOPULATION_VERSION', '1.0.0' );
	
	add_action( 'gform_loaded', array( 'GF_DataPopulation_Bootstrap', 'load' ), 5 );
	
	class GF_DataPopulation_Bootstrap {
	
		public static function load(){
			require_once( 'class-gf-datapopulation.php' );
			GFAddOn::register( 'GFDataPopulation' );
		}
	
	}