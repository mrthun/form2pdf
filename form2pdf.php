<?php
    /*
     * Plugin Name: Form2PDF
     * Plugin URI: http://mrthun.com
     * Description: Exports Ninja Form submissions to PDF
     * Version: 0.1
     * Author: Christian Thun
     * Author URI: http://mrthun.com
     * License: GPLv2
     */
    /*
     * LICENSE
     *
     * Copyright (C) 2013  Christian Thun (christian@mrthun.net)
     * This program is free software; you can redistribute it and/or
     * modify it under the terms of the GNU General Public License
     * as published by the Free Software Foundation; either version 2
     * of the License, or (at your option) any later version.
     *
     * This program is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     * GNU General Public License for more details.
     
     * You should have received a copy of the GNU General Public License
     * along with this program; if not, write to the Free Software
     * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
     */
     
    
    
    if(!class_exists('Form2pdf')) {
        
        class Form2pdf {
            var $plugin_url;
            var $plugin_dir;
			var $pdfurl;
			var $templateurl;
            var $db_opt = 'Form2pdf_Options';
			var $debug_out = array();
            
            public function __construct() {
                $this->plugin_url = trailingslashit( WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) );
                $this->plugin_dir = trailingslashit( plugin_dir_path(__FILE__) );
				$this->pdfurl = $this->plugin_dir . 'pdf/';
				$this->templateurl = $this->plugin_dir . 'templates/';
				$this->select_boxes = configure_select();
                
				// $this->debug_add('BOXES'.$this->select_boxes);
				$this->debug_add('PATH',$this->plugin_dir . 'includes/form2pdf-options.php');
                
          //      if(is_admin()) {
                    add_action('admin_init', array($this, 'register_settings'));
                    add_action('admin_menu', array($this, 'options_page'));
        //        } else {
                    add_action('wp_footer', array($this,'ct_debug'));
					add_action( 'init', array($this,'ninja_forms_create_pdf_init') );
					


          //      }
            }
			
			
			function ninja_forms_create_pdf_init(){
    			add_action( 'ninja_forms_before_pre_process', array($this,'ninja_forms_create_pdf' ));
				
				
			}
 
	function ninja_forms_create_pdf(){
    	global $ninja_forms_processing,$ninja_forms_fields;
 
		$form_id = $ninja_forms_processing->get_form_ID();
		$field_results = ninja_forms_get_fields_by_form_id($form_id);
		$options = $this->get_options();
		$file_name='';
	
		if($options['template'] !=='' && $options['form_id']==$form_id && $options['convert']==true && is_array( $field_results ) AND !empty( $field_results )) {
					
			$this->debug_add('AJAX SUBMIT','test');
			
			// Create first part of filename
			$form_row = ninja_forms_get_form_by_id( $form_id );
			$form_data = $form_row['data'];
			if( isset( $form_data['form_title'] ) ){
				$form_title = $form_data['form_title'];
			}else{
				$form_title .= $form_id;
			}
			// no spaces
			$file_name = str_replace(' ','_',$form_title);
			
			// get the template			
			if (is_readable($this->templateurl . $options['template'])) {
				$file = fopen($this->templateurl . $options['template'], "r");
				$template = fread($file, filesize($this->templateurl . $options['template']));
				fclose($file);
			} else {
				$this->debug_add('FILE NOT READABLE', $this->templateurl . $options['template']);
			}			
	
			// cycle through the submission and do the magic
			foreach( $field_results as $field ){
				$field_id = $field['id'];
				$field_type = $field['type'];
				$field_data = $field['data'];

				if( isset( $ninja_forms_fields[$field_type] ) ){
					$reg_field = $ninja_forms_fields[$field_type];
					
					$pre_process_function = $reg_field['pre_process'];
					if($pre_process_function != ''){
						$arguments = array();
						$arguments['field_id'] = $field_id;
						$user_value = $ninja_forms_processing->get_field_value( $field_id );
						$user_value = apply_filters( 'ninja_forms_field_pre_process_user_value', $user_value, $field_id );
						// $this->debug_add('FIELD',$user_value);
						$arguments['user_value'] = $user_value;
						call_user_func_array($pre_process_function, $arguments);
						
						// Replace all value-tags for this field in the template
						$template = str_replace('%v:' . $field_id . '%',$user_value,$template);
						// Replace all label-tags for this field in the template
						$template = str_replace('%l:' . $field_id . '%',$field_data['label'],$template);
						
						
						// If there was a field set with content to be added to the file name then add it now
						if ($options['name_field'] == $field_id) {
							$file_name .= $user_value;	
						}
						// Either update the filename or the url_field contents depending on settings
						if ($options['url_field'] == $field_id) {
							if ($options['store_versions'] == 0 && $user_value !=='' && !(empty($user_value))) {
								// we don't store versions so we  reuse the existing filename
								$file_name = $user_value;
							} else {
								// create the new filename and update the field
								// Add the timestamp to the filename -  only if there is a filename of course
								$date = new DateTime();
								$file_name .= $date->getTimestamp() . '.pdf';
								// Now update the field in the submission
								$ninja_forms_processing->update_field_value($field_id, "wp-content/plugins/form2pdf/pdf/" . $file_name);
							
							}							
						}					
					}				
				}
			}
			require_once($this->plugin_dir . "includes/dompdf/dompdf_config.inc.php");
			
			// Create the PDF
			if ($file_name !== '' &&	 $template !== '') {
				$dompdf = new DOMPDF();
				$dompdf->load_html($template);
				$dompdf->render();
				$output = $dompdf->output();		
				file_put_contents($this->pdfurl . $file_name, $output);

			}
		}
	}

	function get_the_list($id = '', $the_title = '', $palceholder = '',$content = NULL) {
		$the_list='<ul id="' . $id . '"><li><p>' . $the_title . '</p><select data-placeholder="' . $placeholder . '">';
		foreach($content as $content_item) {
			$the_list .= '<option data-connection="' 
			. $content['item'] . '" value="' 
			. $content['value'] . '">' 
			. $content['name'] . '</options>';
		}
		$the_list .= '</select></li></ul>';
	}
			
            function debug_add($title, $content)     {
            	array_push($this->debug_out,array($title,$content));
            }   
			 
            function ct_debug() {
            	// var_dump($this->debug_out);
            	foreach( $this->debug_out as $output ){
            		if(gettype($output[0])=='string') {
            			echo '<br/>' . $output[0] . '<br/>';
            		}
            		echo '<br/>' . var_dump($output[1]).'<br/>';
					
				}
            }
            
            public function install() {
                $this->get_options();
            }
            
            public function deactivate() {
            }
            
            public function get_options() {
                $options = array(
                                 'debug' => 0,
                                 'form_id' => 0,
                                 'convert' => 0,
                                 'store_versions' => 0,
                                 'name_field' => '',
                                 'url_field' => '',
                                 'template' => '',
                                 );
                $saved = get_option($this->db_opt);
                if(!empty($saved)) {
                    foreach ($saved as $key => $option) {
                        $options[$key] = $option;
                    }
                }
                if($saved != $options) {
                    update_option($this->db_opt, $options);
                }
                return $options;
            }
            
            function options_page() {
                add_options_page('Form2PDF Settings', 'Form2pdf', 'manage_options', 'form2pdf', array($this, 'handle_options'));
            }
            
            function register_settings() {
                register_setting('Form2pdf_Options', $this->db_opt, array($this, 'validate_options'));
                

                add_settings_section(
                                     'form_settings',
                                     'Form Settings',
                                     array($this, 'form_settings_text'),
                                     'form2pdf'
                                     );
                add_settings_field(
                                   'form_id',
                                   'Form ID',
                                   array($this, 'form_id_input'),
                                   'form2pdf',
                                   'form_settings'
                                   );
                add_settings_field(
                                   'convert',
                                   'Convert',
                                   array($this, 'convert_select'),
                                   'form2pdf',
                                   'form_settings'
                                   );
                add_settings_field(
                                   'url_field',
                                   'Field where URL to PDF file is stored',
                                   array($this, 'url_field_input'),
                                   'form2pdf',
                                   'form_settings'
                                   );
				add_settings_field(
                                   'store_versions',
                                   'Store a new version with each submission',
                                   array($this, 'version_select'),
                                   'form2pdf',
                                   'form_settings'
                                   );
                add_settings_field(
                                   'name_field',
                                   'Field that should be added to the filename',
                                   array($this, 'name_field_input'),
                                   'form2pdf',
                                   'form_settings'
                                   );
				add_settings_field(
                                   'template_field',
                                   'Template Filename',
                                   array($this, 'template_field_input'),
                                   'form2pdf',
                                   'form_settings'
                                   );
                
                
                

                add_settings_section(
                                     'debug_it',
                                     'Debugging',
                                     array($this, 'debug_it_text'),
                                     'form2pdf'
                                     );
                
                add_settings_field(
                                   'debug',
                                   'Enable Debugging',
                                   array($this, 'debug_select'),
                                   'form2pdf',
                                   'debug_it'
                                   );

            }
            
            function debug_it_text() {
                echo 'Enable to show debug information in the footer and the ZIP search field in the menu bar';
                
            }
            
            function debug_select() {
                $options = $this->get_options();
                
                echo '<div><select name="'.$this->db_opt.'[debug]" id="debug"><option value="0"'.($options['debug'] == 0 ? ' selected="selected"' : '').'>No</option><option value="1"'.($options['debug'] == 1 ? ' selected="selected"' : '').'>Yes</option></select></div>';
            }
            
            function form_settings_text() {
                echo 'Select the form for which you want to convert submissions to PDF and the field where the PDF url is to be stored.';
            }
            function url_field_input() {
                $options = $this->get_options();
                
                echo '<input type="text" id="url_field" name="' . $this->db_opt . '[url_field]" value="' . $options['url_field'] . '" size="25" />';
            }
            function convert_select() {
                $options = $this->get_options();
                
                echo '<div><select name="'.$this->db_opt.'[convert]" id="convert"><option value="0"'.($options['convert'] == 0 ? ' selected="selected"' : '').'>No</option><option value="1"'.($options['convert'] == 1 ? ' selected="selected"' : '').'>Yes</option></select></div>';
            }
            function form_id_input() {
                $options = $this->get_options();
                
                echo '<input type="text" id="form_id" name="' . $this->db_opt . '[form_id]" value="' . $options['form_id'] . '" size="25" />';
            }
            function version_select() {
                $options = $this->get_options();
                
                echo '<div><select name="'.$this->db_opt.'[store_versions]" id="store_versions"><option value="0"'.($options['store_versions'] == 0 ? ' selected="selected"' : '').'>No</option><option value="1"'.($options['store_versions'] == 1 ? ' selected="selected"' : '').'>Yes</option></select></div>';
            }
            function name_field_input() {
                $options = $this->get_options();
                
                echo '<input type="text" id="name_field" name="' . $this->db_opt . '[name_field]" value="' . $options['name_field'] . '" size="25" />';
            }
             function template_field_input() {
                $options = $this->get_options();
                
                echo '<input type="text" id="template_field" name="' . $this->db_opt . '[template]" value="' . $options['template'] . '" size="25" />';
            }           


            function validate_options($input) {
                $valid['debug'] = intval($input['debug']);
                $valid['convert'] = intval($input['convert']);
                $valid['template'] = $input['template'];
                
                // TO BE FIXED!!!!
                $valid['url_field'] = $input['url_field'];
                $valid['form_id'] = $input['form_id'];
				
				$valid['name_field'] = $input['name_field'];
				$valid['store_versions'] = intval($input['store_versions']);

                return $valid;
            }
            
            public function handle_options() {
                $settings = $this->db_opt;
                include_once($this->plugin_dir . 'includes/form2pdf-options.php');
            }
            
        }
            
            
    }

	class SelectBox{
    	public $items = array();
    	public $defaultText = '';
    	public $title = '';

    	public function __construct($title, $default){
        	$this->defaultText = $default;
        	$this->title = $title;
    	}

    	public function addItem($name, $connection = NULL){
        	$this->items[$name] = $connection;
        	return $this;
    	}

    	public function toJSON(){
        	return json_encode($this);
    	}
		
	}
	
				
	function configure_select() {
		$select = new SelectBox('What would you like to purchase?','Choose a product category');
		$select	->addItem('Phones','phoneSelect')
              			->addItem('Notebooks','notebookSelect')
              			->addItem('Tablets','tabletSelect');
		return $select;
	}
	


    $Form2pdf = new Form2pdf();
    
    if($Form2pdf) {  	
        register_activation_hook( __FILE__, array(&$Form2pdf, 'install'));
        // Adds an ajax actions.
    }
    
?>