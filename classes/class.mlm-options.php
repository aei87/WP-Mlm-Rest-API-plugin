<?php 

class MlmOptions {

	
	public $options = array();

	public function __construct() {

		/*  Setting up actions... */	
		//add_action( 'admin_menu', array( $this, 'mlm_add_options' )  );
		//add_action( 'admin_init', array( $this, 'mlm_register_settings' ) );

		$this->init();

	} 


	/**
	   Get Wp options from Custom Settings page
	**/

	public function init() {  
 	
 		$this->options['plugin_prefix'] = 'MlmPlugin_'; 
 		$this->options['plugin_path'] = plugin_dir_path( __DIR__ ); 
 		$this->options['blog_email'] = 'admin@mlm.fvds.ru';  
 		$this->options['blog_name'] = get_bloginfo('name'); 

		$this->options['text_input'] = array('id' => $this->options['plugin_prefix'].'text_input', 'label' => 'Text input', 'type' => 'textfield', 'after_label' => '', 'value' => false); 
		
		$this->options['password_input'] = array('id' => $this->options['plugin_prefix'].'password_input', 'label' => 'Password input', 'type' => 'password', 'after_label' => 'Normal account password (NO MD5 Hash!)','value' => false); 
		
		$this->options['manual_referral'] = array('id' => $this->options['plugin_prefix'].'referral_required', 'label' => 'Manual referral code input field', 'type' => 'checkbox', 'after_label' => '','value' => false); 

		$this->options['complex_repeater_input'] = array('id' => $this->options['plugin_prefix'].'complex_repeater_input', 'label' => 'Complex repeater input', 'type' => 'complex_repeater_input', 'after_label' => '', 'value' => false);

		add_action( 'pre_update_option_'.$this->options['complex_repeater_input']['id'].'', array( $this, 'pre_update_complex_repeater_input' ), 10, 2 );


		foreach ($this->options as $key => $option) {
			if (is_array($this->options[$key])) {
				$this->options[$key]['value'] = get_option($option['id']);
			}
		}

		return $this->options;
	}


	/**
	   * Options
	*/

	public function mlm_add_options() {  
		add_menu_page( 'Mlm options', 'Mlm options', 'manage_options', 'mlm_options_page', array( $this, 'mlm_options_callback' ), '', 4);
	}





	/**
	   * Options
	*/

	public function mlm_options_callback() {  
 	
 	?>
 		<div class="mlm_options">
	 		<h1> Mlm авторизация Open ID </h1>
	 		<h2> </h2>
			<form action="options.php" class="repeater" method="POST">
				<?php
					settings_fields("mlm_options");     // add hidden nonces etc.. Used when register options in DB
					do_settings_sections("mlm_options_page"); // add sections with options
					submit_button();
				?>
			</form>
		</div>

		<style type="text/css">
			
			/* WP options */

			.mlm_options{
			    padding:30px;
			    padding-top:50px;
			}

			.mlm_label{
				margin-left: 30px;
			}

		</style>

	<?php

	}




	/**
	   * Options
	*/

	public function mlm_register_settings()
	{

	    //Add sections
		add_settings_section( 'mlm_section_1', '', array( $this, 'mlm_section_callback_function' ), 'mlm_options_page' );

		foreach ($this->options as $key => $option) {
			
			if (is_array($this->options[$key])) {

				// register options into DB
		    	register_setting( 'mlm_options', $option['id'] );

			 	//add a particual field #1

				add_settings_field( 
					$option['id'], 
					(isset($option['label'])) ? $option['label'] : $key, 
					array( $this, 'callback_for_'.$option['type']), 
					'mlm_options_page', 
					'mlm_section_1', 
					array( 
						'id' => $option['id'], 
						'after_label' => $option['after_label']
					)
				);

			}

		} 

	}




	/**
	  Callback for particular option # 1
	*/

	public function callback_for_textfield( $arg ){ 
			
			?><input type="text" name="<?php echo $arg['id'] ?>" id="<?php echo $arg['id'] ?>" value="<?php echo esc_attr( get_option($arg['id']) ) ?>" /> <?php 
			
			if ($arg['after_label']) {
				?>  <span class="after_label"> <?php echo $arg['after_label']; ?> </span> <?php
			}

		}



	/**
	  Callback for particular option # 2
	*/

	public function callback_for_password( $arg ){ 
			
			?><input type="password" name="<?php echo $arg['id'] ?>" id="<?php echo $arg['id'] ?>" value="<?php echo esc_attr( get_option($arg['id']) ) ?>" /> <?php 
			
			if ($arg['after_label']) {
				?>  <span class="after_label"> <?php echo $arg['after_label']; ?> </span> <?php
			}

		}


	/**
	  Callback for particular option # 3
	*/

	public function callback_for_checkbox( $arg ){ 

			?><input type="checkbox" id="<?php echo $arg['id'] ?>" name="<?php echo $arg['id'] ?>" value="1" <?php checked( '1' == get_option($arg['id']) ); ?> /> <?php
			
			if ($arg['after_label']) {
				?> <span class="after_label"> <?php echo $arg['after_label']; ?> </span> <?php
			}
		}


	/**
	  Callback for particular option # 4
	*/

	public function callback_for_complex_repeater_input( $arg ){
		
		?>
		<script src="<?php echo plugin_dir_url( __DIR__ ); ?>/js/jquery.repeater.js"></script>
		<style type="text/css">
			
			.add_repeater{
				margin-top:10px;
			}

		</style>

		<div data-repeater-list="users">
			
			<?php

			$current_users = get_option($arg['id']);
			$current_users = json_decode($current_users, true);

			if (!empty($current_users)) { 

				foreach ($current_users as $key => $user) {

						?>
						
						<div data-repeater-item>

				            <select style="margin-top:-2px;" name="user">
				            	<?php

			        				$users = get_users(array('fields' => array( 'ID', 'user_nicename')));
									foreach($users as $user_id){
									    ?> <option value="<?php echo $user_id->ID; ?>" <?php selected( $user_id->ID, $user['user'] ); ?> ><?php echo $user_id->user_nicename; ?></option> <?php
									}

				            	?>
				            </select>

				            <input type="text" name="phone" value="<?php echo $user['phone']; ?>"/>
				            <input data-repeater-delete type="button" value="Delete"/>
				        </div>

						<?php
				}
			}
			else{
					
				?>

				<div data-repeater-item>

		            <select style="margin-top:-2px;" name="user">
		            	<option value=""></option>
		            	<?php

	        				$users = get_users(array('fields' => array( 'ID', 'user_nicename')));
							foreach($users as $user_id){
							    ?> <option value="<?php echo $user_id->ID; ?>"><?php echo $user_id->user_nicename; ?></option> <?php
							}

		            	?>
		            </select>

		            <input type="text" name="phone" value=""/>
		            <input data-repeater-delete type="button" value="Delete"/>
		        </div>

		        <?php

			}

			?>
				          
	    </div>

	    <input data-repeater-create class="add_repeater" type="button" value="Add"/>	 

	    <script>
	    
	    jQuery(document).ready(function () { 
	      
	        jQuery('.repeater').repeater({
	            defaultValues: {
	                'phone': '',
	                'user': ''
	            },
	            show: function () {
	                jQuery(this).slideDown();
	            },
	            hide: function (deleteElement) {
	                if(confirm('Are you sure you want to delete this phone?')) {
	                    jQuery(this).slideUp(deleteElement);
	                }
	            }
	        });
	    });

	    </script>

	    <?php

	}



	/**
	  
	*/

	public function pre_update_complex_repeater_input( $value, $old_value )
	{
	   
	   $users = array();

	   foreach ($_POST['users'] as $key => $user) {
	   	 if ((!empty($user['user'])) && (!empty($user['phone']))) {
	   	 	$users[$user['user']] = $user;
	   	 }
	   }

	   $result = wp_json_encode($users);
	   return $result;
	}



	/**
	  Callback to insert a section label only
	*/

	public function mlm_section_callback_function( $val ){ 
		echo 'Настройки плагина';
	}


}

?>