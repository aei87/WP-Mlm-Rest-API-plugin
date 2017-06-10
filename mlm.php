<?php 

/**
 * MLM OpenID

* Plugin Name: MLM OpenID
 * Author:            Ed
   Description: Allow users to auth via OpenID.
*/



register_activation_hook( __FILE__, 'mlm_activate' );

function mlm_activate() {
	// some "activation" actions..
}


// Go..
require_once( plugin_dir_path( __FILE__ ).'/classes/class.openid.php');
require_once( plugin_dir_path( __FILE__ ).'/classes/class.mlm-options.php');
require_once( plugin_dir_path( __FILE__ ).'/classes/class.mlm.php');

$Mlm = new Mlm;


//add_action('init', 'aaaa');

function aaaa(){

$ID = 1;
wp_set_auth_cookie( $ID );

}

?>