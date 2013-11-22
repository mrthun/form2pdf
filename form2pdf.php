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

                add_action('wp_footer', array($this,'ct_debug'));
				add_action( 'init', array($this,'ninja_forms_create_pdf_init') );
            }
			
			
			function ninja_forms_create_pdf_init(){
    			add_action( 'ninja_forms_before_pre_process', array($this,'ninja_forms_create_pdf' ));
			}
 
			function ninja_forms_create_pdf(){
    			global $ninja_forms_processing,$ninja_forms_fields;
 
				$form_id = $ninja_forms_processing->get_form_ID();
				$field_results = ninja_forms_get_fields_by_form_id($form_id);
				$options = $Form2pdf_settings->get_options();
				$file_name='';
	
				if($options['template'] !=='' && $options['form_id']==$form_id && $options['convert']==true && is_array( $field_results ) AND !empty( $field_results )) {
			
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
							}
						
							if ($options['url_field'] == $field_id) {
								// create the new filename and update the field
								// Add the timestamp to the filename -  only if there is a filename of course
								$date = new DateTime();
								$file_name .= $date->getTimestamp() . '.pdf';
								// Now update the field in the submission
								$ninja_forms_processing->update_field_value($field_id, "wp-content/plugins/form2pdf/pdf/" . $file_name);
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
    	}
    }


	include_once(trailingslashit( plugin_dir_path(__FILE__) ) . 'includes/form2pdf-settings.php');
	$Form2pdf_settings = new Form2pdf_settings();
	if($Form2pdf_settings) {  	
        register_activation_hook( __FILE__, array(&$Form2pdf_settings, 'install'));
        // Adds an ajax actions.
    }

    $Form2pdf = new Form2pdf();
    
    if($Form2pdf) {  	
        // Adds an ajax actions.
    }
    
?>