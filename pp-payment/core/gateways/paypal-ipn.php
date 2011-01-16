<?php
/**
 *
 * 
 * @package Prospress
 * @version 1.1
 **/

/**
 * I sit and lookout at PayPal notifications. If the page request is from PayPal
 * return the parameters sent with the request to confirm the transaction as required by
 * PayPal's IPN.  
 *
 * Code based on PayPal example here: https://cms.paypal.com/cms_content/US/en_US/files/developer/IPN_PHP_41.txt
 **/
function pp_paypal_ipn_listener(){
	global $wpdb;

	error_log( 'in paypal ipn listener' );
	error_log( 'POST = ' . print_r( $_POST, true ) );
	error_log( 'GET = ' . print_r( $_GET, true ) );

	// read the post from PayPal system and add 'cmd'
	$req = 'cmd=_notify-validate';

	foreach ( $_POST as $key => $value ) {
		$value = urlencode( stripslashes( $value ) );
		$req .= "&$key=$value";
	}

	// post back to PayPal system to validate
	$header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
	$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

	// Determine if sandbox mode should be used
	$user_id = get_post( $_POST[ 'item_number' ] )->post_author;
	$pp_invoice_settings = get_usermeta( $user_id, 'pp_invoice_settings' );
	$paypal_sandbox = $pp_invoice_settings[ 'paypal_sandbox' ];

	$fp_url	= 'ssl://www.' . ( $paypal_sandbox == 'true' ) ? "sandbox." : '';
	$fp_url	.= 'paypal.com';
	$fp 	= fsockopen( $fp_url, 443, $errno, $errstr, 30 );

	if ( !$fp ) {
		// HTTP ERROR
		error_log('There has been a HTTP error with PayPal IPN: $req = ' . print_r( $req, true ) );
		error_log('There has been a HTTP error with PayPal IPN: $_POST = ' . print_r( $_POST, true ) );
	} else {
		fputs ( $fp, $header . $req );
		while ( !feof( $fp ) ) {
			$res = fgets( $fp, 1024 );
			if ( strcmp( $res, "VERIFIED" ) == 0 ) {
				do_action( 'paypal_ipn_verified', $_POST );
				add_post_meta( $_POST['item_number'], 'paypal_ipn_invalid', $_POST );
			} else if ( strcmp ( $res, "INVALID" ) == 0 ) {
				// log for manual investigation
				add_post_meta( $_POST['item_number'], 'paypal_ipn_invalid', $_POST );
				do_action( 'paypal_ipn_invalid', $_POST );
			}
		}
		fclose( $fp );
	}
}