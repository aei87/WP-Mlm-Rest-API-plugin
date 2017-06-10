<?
/*
	FREE TO USE
	Simple OpenID PHP Class
	Contributed by http://www.fivestores.com/
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

This Class was written to make easy for you to integrate OpenID on your website. 
This is just a client, which checks for user's identity. This Class Requires CURL Module.
It should be easy to use some other HTTP Request Method, but remember, often OpenID servers
are using SSL.
We need to be able to perform SSL Verification on the background to check for valid signature.

HOW TO USE THIS CLASS:
  STEP 1)
	$openid = new SimpleOpenID;
	:: SET IDENTITY ::
		$openid->SetIdentity($_POST['openid_url']);
	:: SET RETURN URL ::
		$openid->SetApprovedURL('http://www.yoursite.com/return.php'); // Script which handles a response from OpenID Server
	:: SET TRUST ROOT ::
		$openid->SetTrustRoot('http://www.yoursite.com/');
	:: FETCH SERVER URL FROM IDENTITY PAGE ::  [Note: It is recomended to cache this (Session, Cookie, Database)]
		$openid->GetOpenIDServer(); // Returns false if server is not found
	:: REDIRECT USER TO OPEN ID SERVER FOR APPROVAL ::
	
	:: (OPTIONAL) SET OPENID SERVER ::
		$openid->SetOpenIDServer($server_url); // If you have cached previously this, you don't have to call GetOpenIDServer and set value this directly
		
	STEP 2)
	Once user gets returned we must validate signature
	:: VALIDATE REQUEST ::
		true|false = $openid->ValidateWithServer();
		
	ERRORS:
		array = $openid->GetError(); 	// Get latest Error code
	
	FIELDS:
		OpenID allowes you to retreive a profile. To set what fields you'd like to get use (accepts either string or array):
		$openid->SetRequiredFields(array('email','fullname','dob','gender','postcode','country','language','timezone'));
		 or
		$openid->SetOptionalFields('postcode');
		
IMPORTANT TIPS:
OPENID as is now, is not trust system. It is a great single-sign on method. If you want to 
store information about OpenID in your database for later use, make sure you handle url identities
properly.
  For example:
	https://steve.myopenid.com/
	https://steve.myopenid.com
	http://steve.myopenid.com/
	http://steve.myopenid.com
	... are representing one single user. Some OpenIDs can be in format openidserver.com/users/user/ - keep this in mind when storing identities

	To help you store an OpenID in your DB, you can use function:
		$openid_db_safe = $openid->OpenID_Standarize($upenid);
	This may not be comatible with current specs, but it works in current enviroment. Use this function to get openid
	in one format like steve.myopenid.com (without trailing slashes and http/https).
	Use output to insert Identity to database. Don't use this for validation - it may fail.

*/

class SimpleOpenID{
	
	var $openid_url_identity;
	var $URLs = array();
	var $error = array();
	var $fields = array();
	
	
	/*  SimpleOpenID  */

	function SimpleOpenID(){

		if (!function_exists('curl_exec')) {
			die('Error: Class SimpleOpenID requires curl extension to work');
		} 
	}
	

	/*  SetOpenIDServer  */

	function SetOpenIDServer($a){

		$this->URLs['openid_server'] = $a;
	}
	

	/*  SetTrustRoot  */

	function SetTrustRoot($a){

		$this->URLs['trust_root'] = $a;
	}
	

	/*  SetCancelURL  */

	function SetCancelURL($a){

		$this->URLs['cancel'] = $a;
	}
	

	/*  SetApprovedURL  */

	function SetApprovedURL($a){

		$this->URLs['approved'] = $a;
	}
	

	/*  SetRequiredFields  */

	function SetRequiredFields($a){

		if (is_array($a)){
			$this->fields['required'] = $a;
		}
		else{
			$this->fields['required'][] = $a;
		}
	}
	

	/*  SetOptionalFields  */

	function SetOptionalFields($a){

		if (is_array($a)){
			$this->fields['optional'] = $a;
		}
		else{
			$this->fields['optional'][] = $a;
		}
	}
	

	/*  SetIdentity  */

	function SetIdentity($a){

			/*
 			if(strpos($a, 'http://') === false) {
		 		$a = 'http://'.$a; 
		 	}
		 	*/
			$this->openid_url_identity = $a;
	}
	

	/*  GetIdentity  */

	function GetIdentity(){ 

		return $this->openid_url_identity;
	}
	

	/*  GetError  */

	function GetError(){

		$e = $this->error;
		return array('code'=>$e[0],'description'=>$e[1]);
	}

	
	/*  ErrorStore  */

	function ErrorStore($code, $desc = null){

		$errs['OPENID_NOSERVERSFOUND'] = 'Cannot find OpenID Server TAG on Identity page.';
		if ($desc == null){
			$desc = $errs[$code];
		}
	   	$this->error = array($code,$desc);
	}


	/*  IsError  */

	function IsError(){

		if (count($this->error) > 0){
			return true;
		}
		else{
			return false;
		}
	}
	

	/*  splitResponse  */

	function splitResponse($response) {

		$r = array();
		$response = explode("\n", $response);
		foreach($response as $line) {
			$line = trim($line);
			if ($line != "") {
				list($key, $value) = explode(":", $line, 2);
				$r[trim($key)] = trim($value);
			}
		}
	 	return $r;
	}
	
	
	/*  OpenID_Standarize  */

	function OpenID_Standarize($openid_identity){

		$u = parse_url(strtolower(trim($openid_identity)));
		if ($u['path'] == '/'){
			$u['path'] = '';
		}
		if(substr($u['path'],-1,1) == '/'){
			$u['path'] = substr($u['path'], 0, strlen($u['path'])-1);
		}
		if (isset($u['query'])){ // If there is a query string, then use identity as is
			return $u['host'] . $u['path'] . '?' . $u['query'];
		}
		else{
			return $u['host'] . $u['path'];
		}
	}
	
	
	/* array2url  */

	function array2url($arr){

		$query = '';
		
		if (!is_array($arr)){
			return false;
		}
		
		foreach($arr as $key => $value){
			$query .= $key . "=" . $value . "&";
		}
		
		return $query;
	}
	

	/* FSOCK_Request  */

	function FSOCK_Request($url, $method="GET", $params = ""){

		$fp = fsockopen("ssl://www.myopenid.com", 443, $errno, $errstr, 3); // Connection timeout is 3 seconds
		if (!$fp) {
			
			$this->ErrorStore('OPENID_SOCKETERROR', $errstr);
		   	return false;
		} 
		else {
			
			$request = $method . " /server HTTP/1.0\r\n";
			$request .= "User-Agent: Simple OpenID PHP Class (http://www.phpclasses.org/simple_openid)\r\n";
			$request .= "Connection: close\r\n\r\n";
		   	fwrite($fp, $request);
		   	stream_set_timeout($fp, 4); // Connection response timeout is 4 seconds
		   	$res = fread($fp, 2000);
		   	$info = stream_get_meta_data($fp);
		   	fclose($fp);
		
		   	if ($info['timed_out']) {
		       
		       $this->ErrorStore('OPENID_SOCKETTIMEOUT');
		   	} 
		   	else {
		      	return $res;
		   	}
		}
	}
	

	/* CURL_Request  */

	function CURL_Request($url, $method="GET", $params = "") { 

		// Remember, SSL MUST BE SUPPORTED
		if (is_array($params)) $params = $this->array2url($params);
		$curl = curl_init($url . ($method == "GET" && $params != "" ? "?" . $params : ""));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_HTTPGET, ($method == "GET"));
		curl_setopt($curl, CURLOPT_POST, ($method == "POST"));
		if ($method == "POST") curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		
		if (curl_errno($curl) == 0){
			$response;
		}
		else{
			$this->ErrorStore('OPENID_CURL', curl_error($curl));
		}
		return $response;
	}
	
	
	/* HTML2OpenIDServer  */

	function HTML2OpenIDServer($content) {

		$get = array();
		// Get details of their OpenID server and (optional) delegate
		preg_match_all('/<link[^>]*rel="openid.server"[^>]*href="([^"]+)"[^>]*\/?>/i', $content, $matches1);
		preg_match_all('/<link[^>]*href="([^"]+)"[^>]*rel="openid.server"[^>]*\/?>/i', $content, $matches2);
		$servers = array_merge($matches1[1], $matches2[1]);
		
		preg_match_all('/<link[^>]*rel="openid.delegate"[^>]*href="([^"]+)"[^>]*\/?>/i', $content, $matches1);
		
		preg_match_all('/<link[^>]*href="([^"]+)"[^>]*rel="openid.delegate"[^>]*\/?>/i', $content, $matches2);
		
		$delegates = array_merge($matches1[1], $matches2[1]);
		
		$ret = array($servers, $delegates);
		return $ret;
	}
	
	
	/* GetOpenIDServer  */

	function GetOpenIDServer(){

		/*
		$response = $this->CURL_Request($this->openid_url_identity);
		list($servers, $delegates) = $this->HTML2OpenIDServer($response);
		if (count($servers) == 0){
			$this->ErrorStore('OPENID_NOSERVERSFOUND');
			return false;
		}
		if ($delegates[0] != ""){
			$this->openid_url_identity = $delegates[0];
		}
		$this->SetOpenIDServer($servers[0]);
		*/

		$this->SetOpenIDServer('http://lab1.onlineoffice.pro/Login');
		return 'http://lab1.onlineoffice.pro/Login';
		//return $servers[0];
	}
	
	
	/* GetRedirectURL  */

	function GetRedirectURL(){ 

		$params = array();

		/*
		$params['openid.return_to'] = urlencode($this->URLs['approved']);
		$params['openid.mode'] = 'checkid_setup';
		$params['openid.identity'] = urlencode($this->openid_url_identity);
		$params['openid.trust_root'] = urlencode($this->URLs['trust_root']);
		*/

		
		$params['openid.return_to'] = urlencode($this->URLs['approved']);
		$params['openid.mode'] = 'checkid_setup';
		$params['openid.claimed_id'] = urlencode($this->openid_url_identity);
		$params['openid.trust_root'] = urlencode($this->URLs['trust_root']);
		

		/*
		if (count($this->fields['required']) > 0){
			$params['openid.sreg.required'] = implode(',',$this->fields['required']);
		}
		if (count($this->fields['optional']) > 0){
			$params['openid.sreg.optional'] = implode(',',$this->fields['optional']);
		}
		*/

		return $this->URLs['openid_server'] . "?". $this->array2url($params);

	}
	
	
	/* Redirect  */

	function Redirect(){

		$redirect_to = $this->GetRedirectURL();

		if (headers_sent()){
			echo '<script language="JavaScript" type="text/javascript">window.location=\'';
			echo $redirect_to;
			echo '\';</script>';
		}
		else{
			header('Location: ' . $redirect_to);
		}
	}
	

}

?>