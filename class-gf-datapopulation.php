<?php
	
	GFForms::include_addon_framework();
	
	class GFDataPopulation extends GFAddOn {

		protected $_version = GF_DATAPOPULATION_VERSION;
		protected $_min_gravityforms_version = '1.8.16.3';
		protected $_slug = 'gravity-forms-data-population';
		protected $_path = 'gravity-forms-data-population/data-population.php';
		protected $_full_path = __FILE__;
		protected $_url = 'http://travislop.es';
		protected $_title = 'Gravity Forms Data Population';
		protected $_short_title = 'Data Population';
		private $forms_to_populate = array();
		private $field_types_to_populate = array();

		private static $_instance = null;
	
		public static function get_instance() {
			
			if (self::$_instance == null) {
				self::$_instance = new GFDataPopulation();
			}
	
			return self::$_instance;
			
		}

		public function init() {
			
			parent::init();
			
			/* Setup the needed filters and actions if the user is logged in */
			if ( is_user_logged_in() ) {
			
				/* Get form fields available for population */
				add_filter( 'gform_pre_render', array( $this, 'get_form_fields' ), 10, 1 );
				
				/* Add admin bar links */
				add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_links' ), 9999 );
				
				/* Add form population queue JSON to footer */
				add_action( 'wp_footer', array( $this, 'add_json_to_footer' ), 10 );
				
			}
			
		}

		/* Enqueue necessary scripts */
		public function scripts() {
			
			$scripts = array(
				array(
					'handle'		=>	'gfdatapopulation',
					'deps'			=>	array( 'jquery' ),
					'src'			=>	$this->get_base_url() . '/js/gfdatapopulation.js',
					'version'		=>	$this->_version,
					'enqueue'		=>	array(
						array(
							'field_types'		=>	$this->field_types_to_populate
						)
					)
				)
			);
			
			return array_merge( parent::scripts(), $scripts );
			
		}
		
		/* Get form fields available for population */
		function get_form_fields( $form ) {

			/* If this form is already in the population queue, exit now. */
			if ( array_key_exists( $form['id'], $this->forms_to_populate ) ) return $form;
			
			/* Prep this form to be added to the population queue */
			$form_to_populate = array( 
				'id'       => $form['id'],
				'title'     => $form['title'],
				'fields'   => array()	
			);
			
			/* Get sample user data */
			$sample_user_data = wp_remote_get( 'http://api.randomuser.me/?results=1' );
			
			/* If user data request returns an error, default to default data */
			if ( is_a( $sample_user_data, 'WP_Error' ) ) {
				$this->_full_dir = pathinfo( $this->_full_path );
				$sample_user_data = json_decode( file_get_contents( trailingslashit( $this->_full_dir['dirname'] ) . 'inc/user-data.json' ), true );
			} else {
				$sample_user_data = json_decode( $sample_user_data['body'], true );
			}
			
			/* Filter down to the first result */
			$sample_user_data = $sample_user_data['results'][0]['user'];
		
			/* Add sample data to population queue based on field type */
			foreach( $form['fields'] as &$field ) {
				
				/* Field Type: Email Address */
				if ( $field['type'] === 'email' ) {
					$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id']] = $sample_user_data['email'];
				}
				
				/* Field Type: Address */
				if ( $field['type'] === 'address' ) {
					
					foreach ( $field['inputs'] as $input ) {
						
						list( $field_id, $subfield_id ) = explode( '.', $input['id'] );
						
						/* Set field value based on subfield */
						switch ( $subfield_id ) {
							
							case '1':
								$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] = ucwords( $sample_user_data['location']['street'] );
								break;
							case '3':
								$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] = ucwords( $sample_user_data['location']['city'] );
								break;
							case '4':
								$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] = ucwords( $sample_user_data['location']['state'] );
								break;
							case '5':
								$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] = $sample_user_data['location']['zip'];
								break;
							
						}
						
					}
					
				}
				
				/* Field Type: Name */
				if ( $field['type'] === 'name' ) {
					
					foreach ( $field['inputs'] as $input ) {
						
						list( $field_id, $subfield_id ) = explode( '.', $input['id'] );
						
						/* Set field value based on subfield */
						switch ( $subfield_id ) {
							
							case '2':
								$sample_user_data['name']['title'] .= ( $sample_user_data['name']['title'] != 'miss' ) ? '.' : '';
								$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] = ucwords( $sample_user_data['name']['title'] );
								break;
							case '3':
								$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] = ucwords( $sample_user_data['name']['first'] );
								break;
							case '6':
								$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] = ucwords( $sample_user_data['name']['last'] );
								break;
							
						}
						
					}
					
				}
			
				/* Field Type: Phone */
				if ( $field['type'] === 'phone' ) {
					$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id']] = str_replace( ')-', ') ', $sample_user_data['phone'] );
				}
				
				/* Apply filters for sample data */
				if ( empty ( $field['inputs'] ) ) {
					
					$field_value = ( array_key_exists( 'input_'. $form['id'] .'_'. $field['id'], $form_to_populate['fields'] ) ) ? $form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id']] : '';
					
					$field_value = apply_filters( 'gf_datapopulation_value_' . $form['id'] .'_'. $field['id'], apply_filters( 'gf_datapopulation_value_' . $form['id'], apply_filters( 'gf_datapopulation_value', $field_value, $form, $field ), $form, $field ), $form, $field );
					
					$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id']] = $field_value;		
								
				} else {
					
					foreach ( $field['inputs'] as $input ) {
						
						list( $field_id, $subfield_id ) = explode( '.', $input['id'] );
			
						$field_value = ( array_key_exists( 'input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id, $form_to_populate['fields'] ) ) ? $form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] : '';
					
						$field_value = apply_filters( 'gf_datapopulation_value_' . $form['id'] .'_'. $field['id'] .'_'. $subfield_id, apply_filters( 'gf_datapopulation_value_' . $form['id'], apply_filters( 'gf_datapopulation_value', $field_value, $form, $field ), $form, $field ), $form, $field );
					
						$form_to_populate['fields']['input_'. $form['id'] .'_'. $field['id'] .'_'. $subfield_id] = $field_value;		

					}
					
				}
				
				/* Add field type to field types to populate array to ensure script gets enqueued */
				if ( ! in_array( $field['type'], $this->field_types_to_populate ) )
					$this->field_types_to_populate[] = $field['type'];
				
			}
		
			/* Push this form to the population queue */
			$this->forms_to_populate[ $form['id'] ] = $form_to_populate;
			
			return $form;
			
		}
		
		/* Add admin bar links */
		function add_admin_bar_links() {
			
			global $wp_admin_bar;

			/* If the admin bar isn't showing, don't execute. */ 
		    if ( ! is_admin_bar_showing() ) return;
			
			/* If there are forms to be populated, add them to the admin bar. */
			if ( ! empty( $this->forms_to_populate ) ) {
				
				/* Add parent menu item */
				$wp_admin_bar->add_menu( array(
			        'id'    => 'gf_datapopulation',
			        'meta'  => array(),
			        'title' => 'Populate Forms',
			        'href'  => '#'
				) );
			    
			    /* Push each form to the menu */
			    foreach ( $this->forms_to_populate as $form ) {
				    $wp_admin_bar->add_menu( array(
					    'href'   => '#',
						'id'     => 'gf_datapopulation_'. $form['id'],
						'meta'   => array(
							'onclick'    => 'javascript:gf_datapopulation('. $form['id'] .');return false;'
						),
						'parent' => 'gf_datapopulation',
						'title'  => $form['title']
					) );
			    }
			    
			}
			
		}

		/* Add form population queue JSON to footer */
		function add_json_to_footer() {
			
			/* If the admin bar isn't showing, don't execute. */ 
		    if ( ! is_admin_bar_showing() ) return;
		    
		    /* If there are no forms to populate, don't execute. */
		    if ( empty ( $this->forms_to_populate ) ) return;
		    
		    echo '<script type="text/javascript">var gf_datapopulation_forms = \''. json_encode( $this->forms_to_populate ) .'\';</script>';
			
		}

	}
