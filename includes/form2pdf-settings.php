<?php

/* Takes care of the settings for form2pdf
 * 
 */

    interface wp_settings
    {
    	function install();
		function settings_page();
		function register_settings();
		function validate_settings($input);
		function handle_settings();
		function get_settings();
    }
	
    if(!class_exists('Form2pdf_settings')) {
	
	class Form2pdf_settings implements wp_settings
	{
		
		var $db_opt = 'Form2pdf_Options';
		var $debug_out = array();
		
		public function __construct()
		{
			if(is_admin()) {
            	add_action('admin_init', array($this, 'register_settings'));
                add_action('admin_menu', array($this, 'settings_page'));
        	}
			add_action('admin_footer', array($this,'ct_debug'));
		}
		public function install()
		{
			$this->get_settings();
		}
		
		public function get_settings()
		{
			
			// get the saved settings from Wordpress
            $settings = get_option($this->db_opt);
			// Get all form IDs because they are the keys in our $saved array
			$all_forms = ninja_forms_get_all_forms();			
			// $settings = array();

			foreach($all_forms as $one_form)
			{
				// New form? Add an entry to the settings list
				$form_id = $one_form['id'];
				if ($form_id>0) {
					if ($settings) {
						if (!array_key_exists ($form_id,$settings)) 
						{
							$settings[$form_id] = array(
							'convert' => false, 
							'url_field' => 0, 
							'template' =>'', 
							'form_name' => $one_form['data']['form_title']
					     	);
							// write the settings back to the database
							update_option($this->db_opt, $settings);
						}
					} else {
						$settings[$form_id] = array(
							'convert' => false, 
							'url_field' => 0, 
							'template' =>'', 
							'form_name' => $one_form['data']['form_title']
					     );
						// write the settings back to the database
						update_option($this->db_opt, $settings);
					}					
					
				}
			}
            return $settings;			
		}
		
		public function settings_page()
		{
			add_options_page('Form2PDF Settings', 
				'Form2pdf', 
				'manage_options', 
				'form2pdf', 
				array($this, 'handle_settings'));
		}
		
		public function handle_settings() 
		{
        	$plugin_dir = trailingslashit( plugin_dir_path(__FILE__) );
			// echo $include_dir;
            include_once($plugin_dir . 'form2pdf-options.php');
        }
		
		public function register_settings() {
			$settings = $this->get_settings();
			// delete_option( $this->db_opt); 

         	register_setting('Form2pdf_Options', $this->db_opt, array($this, 'validate_settings'));
			
			add_settings_section(
            	'form_settings',
                'Form Settings',
                 array($this, 'form_settings_text'),
                 'form2pdf'
            );
			
			// This calls the routine that will add all the fields
            add_settings_field(
                'forms',
                'Forms',
                array($this, 'output_form_fields'),
                'form2pdf',
                'form_settings'
            );		
		 }
		 
		public function validate_settings($input) 
		 {
		 	$valid = array();
		 	foreach ($input as $key => $item) {
				if (!array_key_exists('convert', $item))
				{
					$item['convert'] = 'NO';
				} 
				$valid[$key] = $item; 
				if (!array_key_exists('template', $item))
				{
					$item['template'] = '';
				} 
				if (!array_key_exists('url_field', $item))
				{
					$item['url_field'] = 0;
				} 
				$valid[$key] = $item; 
			} 
			// $valid=array();
		 	return $valid;
		 }
		 
		 public function form_settings_text() {
		 	echo 'Select for each form how and if you want the PDF to be created';
		 }
		 
		 public function output_form_fields()
		 {
		 	// Output all formfield - one set per form
		 	$settings = $this->get_settings();
			
			// $this->debug_add('SETTINGS ON',$settings);
			foreach($settings as $key => $setting) {

			echo '<div><br/>' . $setting['form_name'] . '<br/>';
			echo 'Field where the PDF url is stored:<input type="text" id="url_field" name="' 
			. $this->db_opt 
			. '[' . $key . '][url_field]" value="' 
			. $setting['url_field'] 
			. '" size="25" /></div>';
			
			echo 'Template name:<input type="text" id="template" name="' 
			. $this->db_opt 
			. '[' . $key . '][template]" value="' 
			. $setting['template'] 
			. '" size="25" />';
			
			echo '<br/>Convert:<input type="checkbox" id="convert" name="' 
			. $this->db_opt 
			. '[' . $key . '][convert]" value="YES"' 
			. ($setting['convert'] == 'YES' ? 'checked' : '') . '/>';
						
			echo '<input type="hidden" id="form_name" name="'
			. $this->db_opt 
			. '[' . $key . '][form_name]" value="' 
			. $setting['form_name'] 
			. '"/>';		
			}	
			
		 }	
		 
		    function debug_add($title, $content)     {
            	array_push($this->debug_out,array($title,$content));
            }   
			 
            function ct_debug() {

            	foreach( $this->debug_out as $output ){
            		if(gettype($output[0])=='string') {
            			echo '<br/>' . $output[0] . '<br/>';
            		}
            		echo '<br/>' . var_dump($output[1]).'<br/>';
					
				}
            } 	 
	}
}
?>