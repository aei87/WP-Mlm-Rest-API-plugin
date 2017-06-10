<?php 

class Mlm {

	public $user;
	public $options = array();

	public function __construct() {

 		$MlmOptions = new MlmOptions;
		$this->options = $MlmOptions->init();

		define('OAUTH2_CLIENT_ID', '55555');
		define('OAUTH2_CLIENT_SECRET', 'test');

		/*  Setting up actions... */

		add_action('admin_init', array( $this, 'init'));
		add_action('init', array( $this, 'init_session'), 1);
		add_action('login_init', array( $this, 'login'));
		add_filter('authenticate', array( $this, 'auth'));
		remove_filter('authenticate', 'wp_authenticate_username_password',  20, 3 );
		remove_filter('authenticate', 'wp_authenticate_email_password',     20, 3 );
		remove_filter('authenticate', 'wp_authenticate_spam_check',         99    );
		add_shortcode('mlm_referral', array( $this, 'mlm_referral'));
	}


  
    /**
	   * Init
	*/
	public function init() {  

		$this->user = wp_get_current_user();
	}
	

	/* [ tagauth, taglogin, tagget, tagaction, taglog, taglogout, taghook, tagnoform
	*
	OpenID login. Main function which handle GET action (used by hook action). Reduce common login functionality   
	*/

	public function login() { 

		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
		$referral = isset($_REQUEST['referral']) ? $_REQUEST['referral'] : false;
		
		do_action( "login_form_{$action}" );

		$interim_login = isset($_REQUEST['interim-login']);

		switch ($action) {

		case 'logout' :

			check_admin_referer('log-out');
			$user = wp_get_current_user();
			wp_logout();

			if (!empty( $_REQUEST['redirect_to'])) {
				$redirect_to = $_REQUEST['redirect_to'];
			} else {
				$redirect_to = '/';
			}

			$redirect_to = 'http://lab1.onlineoffice.pro/site/logout?return_url='.get_site_url().'/wp-login.php';
			wp_redirect($redirect_to);
			exit();

		case 'login' :
		default:
			
			if (!is_user_logged_in()) {

				$user = wp_signon(array(), '');
				$errors = $user;
				$errors = apply_filters( 'wp_login_errors', $errors, '');
				exit();	
			}
			else{
				wp_safe_redirect('/');
			}
		}

	}
	


	/* [ tagauth, taglogin, tagverification, tagget ]
	*
	Auth function that process get data while login. OpenID verification  
	*/

	public function auth() { 

		$domain = get_site_url();
		$url = get_site_url().'/wp-login.php';

		if(!session_id()) {
        	session_start();
    	}	
 
		if ((!is_user_logged_in()) && (!isset($_GET['openid_fields'])) && ($_GET['action'] !== 'logout')) { 
			
			$openid = new SimpleOpenID;

			$_SESSION['redirect_to'] = array_key_exists('redirect_to', $_REQUEST) ? $_REQUEST['redirect_to'] : null;
			$_SESSION['rememberme']  = true;
			
			if ((isset($_REQUEST['referral'])) && (!empty($_REQUEST['referral']))){

				$_SESSION['referral_value']  = $_REQUEST['referral'];
				$_SESSION['referral_field']  = 'invite_code';
			}

			$openid->SetIdentity('55555');
			$openid->SetTrustRoot($domain);
			
			if ($openid->GetOpenIDServer()){
				$openid->SetApprovedURL($url); 
				$openid->Redirect();
			}
			else{
				$error = $openid->GetError();

				$errors = new WP_Error();
				$errors->add( 'error!', $error['description'], 'error' );
				return $errors;
			}
		}
		else if (isset($_GET['openid_fields'])){

			$verification = array();
			$verification['account_id'] = $_GET['openid_fields']['account_id'];
			$verification['email'] = $_GET['openid_fields']['email'];
			$verification['phone'] = $_GET['openid_fields']['phone'];
			$verification['invite_code'] = $_GET['openid_fields']['invite_code'];

			if ($this->verification($verification)){ 		
				
				$referral = false;

				if (isset($_SESSION['referral_field'])){
					$referral = $this->get_referral($_SESSION['referral_field'], $_SESSION['referral_value']);
				}
				
				$user_id = $this->set_current_user($_GET['openid_fields']['full_name'], $_GET['openid_fields']['full_name'], $_GET['openid_fields']['email'],  $referral);

				wp_set_auth_cookie($user_id, $_SESSION['rememberme']);

				if ( isset( $_SESSION['redirect_to'] ) ) {
					$redirect_to = $_SESSION['redirect_to'];
				} else {
					$redirect_to = admin_url();
				}

				wp_safe_redirect($redirect_to);
				exit();
			}
			else if($openid->IsError() == true){			
				$error = $openid->GetError();

				$errors = new WP_Error();
				$errors->add( 'error!', $error['description'], 'error' );
				return $errors;
			}
			else{			

				$errors = new WP_Error();
				$errors->add( 'error!', 'Invalid authorization', 'error' );
				return $errors;
			}
		}
		else{
			$errors = new WP_Error();
			return $errors;
		}
	}


	/* [ tagauth, taglogin, tagverification, taguser, tagset, tagcurrent, tagmeta, tagfields, tagfield, tagreturn, tagid ] 
	*
	  Set current user. Creates user if it doesn't exist, updates user meta fields. Otherwhise returns user ID   
	*/

	public function set_current_user($user_identity, $user_fullname, $user_email, $referral) { 

		$user = get_user_by( 'login', $user_identity);

		if (!$user){
					
			$userdata = array(
				'user_pass'       => 'k9sdfk23asdf9s', 
				'user_login'      =>  $user_fullname, 
				'user_nicename'   =>  $user_fullname,
				'user_email'      =>  $user_email,
				'display_name'    =>  $user_fullname,
				'nickname'        =>  $user_fullname,
			);

			$user_id = wp_insert_user( $userdata );
			
			if ($referral) {
				
				update_user_meta($user_id, 'refferal_id', $referral['id']);
				update_user_meta($user_id, 'refferal_code', $referral['invite_code']);
				update_user_meta($user_id, 'refferal_data', $referral['data']);
			}

			if( ! is_wp_error( $user_id ) ) {
				return $user_id;
			} else {
				return $user_id->get_error_message();
			} 
		}
		else{
			return $user->data->ID;
		}
	}


	/* [ tagoath2, tagoath, tagget, tagreferral, tagdata, tagrest, tagapi] 
	*
	   Get referral info   
	*/

	public function get_referral($referral_field, $referral_value) { 
	
		$data = $this->get_account_info($referral_field, $referral_value);

		$referral = false;

		if ($data->result->result !== 'ok'){

			$referral['id'] = $data->result->result[0]->account_id;
			$referral['invite_code'] = $data->result->result[0]->account->invite_code;
			$referral['data'] = json_encode($data->result->result[0]);
		}

		return $referral;
	}



	/* [ tagoath2, tagoath, tagget, tagreferral, tagdata, tagrest, tagapi, tagaccount, tagmlm] 
	*
	   Get account info   
	*/

	public function get_account_info($field, $value) { 

		$tokenURL = 'https://lab1.mlm-soft.com/api/rpc/v1';

		/* Get access token */

		$params = array(
		  'secret' => OAUTH2_CLIENT_SECRET
		); 

		$query = array(
		  'jsonrpc' => '2.0',
		  'id' => OAUTH2_CLIENT_ID,
		  'method' => 'get_access_token', 
		  'params' => $params
		);

		$str_data = json_encode($query);
		$token = $this->sendPostData($tokenURL, $str_data);

		/* Get info */

		$params = array(
		  $field => $value
		); 

		$query = array(
		  'jsonrpc' => '2.0',
		  'id' => OAUTH2_CLIENT_ID,
		  'method' => 'account_info',
		  'auth' => $token->result->access_token, 
		  'params' => $params
		);

		$str_data = json_encode($query);
		$info = $this->sendPostData($tokenURL, $str_data);

		return $info;
	}


	/* [ tagoath2, tagoath, tagopen, tagid, tagopenid, tagget, tagverification, tagdata, tagrest, tagapi ] 
	*
	   Rest API verification  
	*/

	public function verification ($verification) { 

		$info = $this->get_account_info('account_id', $verification['account_id']);

		$server_ver_data['account_id'] = strval($info->result->result[0]->account_id);
		$server_ver_data['email'] = $info->result->result[0]->email;
		$server_ver_data['phone'] = $info->result->result[0]->phone;
		$server_ver_data['invite_code'] = $info->result->result[0]->account->invite_code;

		if ($server_ver_data == $verification) {
			return true;
		}

	}


	/* [ tagcurl, tagsetopt, tagoath2, tagoath, tagpost, tagdata, tagrest, tagapi, tagsend ] 
	*
	   Sends POST data via CURL   
	*/
	
	public	function sendPostData($url, $post){
		  $ch = curl_init($url);
		  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");  
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		  curl_setopt($ch, CURLOPT_POSTFIELDS,$post);
		  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
		  $result = curl_exec($ch);
		  curl_close($ch);  
		  return json_decode($result);
		}



	/* [ tagbase, taginit, tagsession ] 
	*
	   Simply inits session   
	*/
	
	public function init_session(){
		
		if(!session_id()) {
        	session_start();
    	}	
	}


	/* [ tagshortcode, tagadd, tagreferral, tagarg, tagagruments, tagargument, tagparam, tagparameters] 
	*
	   Shortcode. Gets referral data
	*/

	/* Mlm shortcode*/
	
	public function mlm_referral($atts){

		if (!is_user_logged_in()) {

			if ((isset($_REQUEST['referral'])) && (!empty($_REQUEST['referral']))){

				$_SESSION['referral_value']  = $_REQUEST['referral'];
				$_SESSION['referral_field']  = 'invite_code';
				$_SESSION['referral_data'] = $this->get_referral($_SESSION['referral_field'], $_SESSION['referral_value']);
			}

			if ((isset($_SESSION['referral_data'])) && ($_SESSION['referral_data'])){ 

				$data = json_decode($_SESSION['referral_data']['data']);

				if ($atts['param'] == 'invite_code') {
					return $data->account->$atts['param'];
				}

				return $data->$atts['param'];
			}

		}

		return false;
	}


}




?>