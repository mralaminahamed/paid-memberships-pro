<?php

global $wpdb, $current_user, $pmpro_msg, $pmpro_msgt, $bfirstname, $blastname, $baddress1, $baddress2, $bcity, $bstate, $bzipcode, $bcountry, $bphone, $bemail, $bconfirmemail, $CardType, $AccountNumber, $ExpirationMonth, $ExpirationYear, $pmpro_requirebilling, $pmpro_billing_order, $pmpro_billing_subscription, $pmpro_billing_level;

// Redirect non-user to the login page; pass the Billing page as the redirect_to query arg.
if ( ! is_user_logged_in() ) {
	$billing_url = pmpro_url( 'billing' );
    wp_redirect( add_query_arg( 'redirect_to', urlencode( $billing_url ), pmpro_login_url() ) );
    exit;
}

// Get the order that was passed in.
$order_id = empty( $_REQUEST['order_id'] ) ? 0 : intval( $_REQUEST['order_id'] );
$pmpro_billing_order = MemberOrder::get_order( $order_id );

if ( empty( $pmpro_billing_order ) ) {
    // We need an order to update. Redirect to the account page.
    wp_redirect( pmpro_url( 'account' ) );
}

// Check that the order belongs to the current user.
if ( $pmpro_billing_order->user_id != $current_user->ID ) {
    // This order doesn't belong to the current user. Redirect to the account page.
    wp_redirect( pmpro_url( 'account' ) );
}

// Make sure that the order is in success status.
if ( $pmpro_billing_order->status != 'success' ) {
    // This order is not in success status. Redirect to the account page.
    wp_redirect( pmpro_url( 'account' ) );
}

// Get the subscription for this order and make sure that it is active.
$pmpro_billing_subscription = PMPro_Subscription::get_subscription_from_subscription_transaction_id( $pmpro_billing_order->subscription_transaction_id, $pmpro_billing_order->gateway, $pmpro_billing_order->gateway_environment );
if ( empty( $pmpro_billing_subscription ) || $pmpro_billing_subscription->get_status() != 'active' ) {
    // This subscription is not active. Redirect to the account page.
    wp_redirect( pmpro_url( 'account' ) );
}

// Get the user's current membership level.
$pmpro_billing_level            = pmpro_getSpecificMembershipLevelForUser( $current_user->ID, $pmpro_billing_order->membership_id );
$current_user->membership_level = $pmpro_billing_level;

//need to be secure?
global $besecure, $gateway, $show_paypal_link, $show_check_payment_instructions;
if (empty($pmpro_billing_order->gateway)) {
    //no order
    $besecure = false;
} elseif ($pmpro_billing_order->gateway == "paypalexpress") {
    $besecure = pmpro_getOption("use_ssl");
    //still they might have website payments pro setup
    if ($gateway == "paypal") {
        //$besecure = true;
    } else {
        //$besecure = false;
        $show_paypal_link = true;
    }
} elseif( $pmpro_billing_order->gateway == 'check' ) {
    $show_check_payment_instructions = true;
} else {
    //$besecure = true;
    $besecure = pmpro_getOption("use_ssl");
}

// this variable is checked sometimes to know if the page should show billing fields
$pmpro_requirebilling = true;

// Set the gateway to the order gateway.
if ( ! empty( $pmpro_billing_order->gateway ) ) {
    $gateway = $pmpro_billing_order->gateway;
} else {
    $gateway = NULL;
}

//enqueue some scripts
wp_enqueue_script( 'jquery.creditCardValidator', plugins_url( '/js/jquery.creditCardValidator.js', dirname( __FILE__ ) ), array( 'jquery' ) );

//action to run extra code for gateways/etc
do_action( 'pmpro_billing_preheader' );

//_x stuff in case they clicked on the image button with their mouse
if (isset($_REQUEST['update-billing']))
    $submit = true;
else
    $submit = false;

if (!$submit && isset($_REQUEST['update-billing_x']))
    $submit = true;

if ($submit === "0")
    $submit = true;

//check their fields if they clicked continue
if ($submit) {
    //load em up (other fields)
    if (isset($_REQUEST['bfirstname']))
        $bfirstname = trim(sanitize_text_field($_REQUEST['bfirstname']));
    if (isset($_REQUEST['blastname']))
        $blastname = trim(sanitize_text_field($_REQUEST['blastname']));
    if (isset($_REQUEST['fullname']))
        $fullname = sanitize_text_field($_REQUEST['fullname']); //honeypot for spammers
    if (isset($_REQUEST['baddress1']))
        $baddress1 = trim(sanitize_text_field($_REQUEST['baddress1']));
    if (isset($_REQUEST['baddress2']))
        $baddress2 = trim(sanitize_text_field($_REQUEST['baddress2']));
    if (isset($_REQUEST['bcity']))
        $bcity = trim(sanitize_text_field($_REQUEST['bcity']));
    if (isset($_REQUEST['bstate']))
        $bstate = trim(sanitize_text_field($_REQUEST['bstate']));
    if (isset($_REQUEST['bzipcode']))
        $bzipcode = trim(sanitize_text_field($_REQUEST['bzipcode']));
    if (isset($_REQUEST['bcountry']))
        $bcountry = trim(sanitize_text_field($_REQUEST['bcountry']));
    if (isset($_REQUEST['bphone']))
        $bphone = trim(sanitize_text_field($_REQUEST['bphone']));
    if (isset($_REQUEST['bemail']))
        $bemail = trim(sanitize_email($_REQUEST['bemail']));
    if (isset($_REQUEST['bconfirmemail']))
        $bconfirmemail = trim(sanitize_email($_REQUEST['bconfirmemail']));
    if (isset($_REQUEST['CardType']))
        $CardType = sanitize_text_field($_REQUEST['CardType']);
    if (isset($_REQUEST['AccountNumber']))
        $AccountNumber = trim(sanitize_text_field($_REQUEST['AccountNumber']));
    if (isset($_REQUEST['ExpirationMonth']))
        $ExpirationMonth = sanitize_text_field($_REQUEST['ExpirationMonth']);
    if (isset($_REQUEST['ExpirationYear']))
        $ExpirationYear = sanitize_text_field($_REQUEST['ExpirationYear']);
    if (isset($_REQUEST['CVV']))
        $CVV = trim(sanitize_text_field($_REQUEST['CVV']));
    
    //avoid warnings for the required fields
    if (!isset($bfirstname))
        $bfirstname = "";
    if (!isset($blastname))
        $blastname = "";
    if (!isset($baddress1))
        $baddress1 = "";
    if (!isset($bcity))
        $bcity = "";
    if (!isset($bstate))
        $bstate = "";
    if (!isset($bzipcode))
        $bzipcode = "";
    if (!isset($bphone))
        $bphone = "";
    if (!isset($bemail))
        $bemail = "";
    if (!isset($bcountry))
        $bcountry = "";
    if (!isset($CardType))
        $CardType = "";
    if (!isset($AccountNumber))
        $AccountNumber = "";
    if (!isset($ExpirationMonth))
        $ExpirationMonth = "";
    if (!isset($ExpirationYear))
        $ExpirationYear = "";
    if (!isset($CVV))
        $CVV = "";

    $pmpro_required_billing_fields = array(
        "bfirstname" => $bfirstname,
        "blastname" => $blastname,
        "baddress1" => $baddress1,
        "bcity" => $bcity,
        "bstate" => $bstate,
        "bzipcode" => $bzipcode,
        "bphone" => $bphone,
        "bemail" => $bemail,
        "bcountry" => $bcountry,
        "CardType" => $CardType,
        "AccountNumber" => $AccountNumber,
        "ExpirationMonth" => $ExpirationMonth,
        "ExpirationYear" => $ExpirationYear,
        "CVV" => $CVV
    );
    
    //filter
    $pmpro_required_billing_fields = apply_filters("pmpro_required_billing_fields", $pmpro_required_billing_fields);
	
    foreach ($pmpro_required_billing_fields as $key => $field) {
        if (!$field) {            
			$missing_billing_field = true;
            break;
        }
    }
	
	// Check reCAPTCHA if needed.
	global $recaptcha, $recaptcha_validated;
	if (  $recaptcha == 2 || ( $recaptcha == 1 && pmpro_isLevelFree( $pmpro_level ) ) ) {
		global $recaptcha_privatekey;
		if ( isset( $_POST["recaptcha_challenge_field"] ) ) {
			//using older recaptcha lib
			$resp = recaptcha_check_answer( $recaptcha_privatekey,
				$_SERVER["REMOTE_ADDR"],
				$_POST["recaptcha_challenge_field"],
				$_POST["recaptcha_response_field"] );

			$recaptcha_valid  = $resp->is_valid;
			$recaptcha_errors = $resp->error;
		} else {
			//using newer recaptcha lib
			// NOTE: In practice, we don't execute this code because
			// we use AJAX to send the data back to the server and set the
			// pmpro_recaptcha_validated session variable, which is checked
			// earlier. We should remove/refactor this code.
			$reCaptcha = new pmpro_ReCaptcha( $recaptcha_privatekey );
			$resp      = $reCaptcha->verifyResponse( $_SERVER["REMOTE_ADDR"], $_POST["g-recaptcha-response"] );

			$recaptcha_valid  = $resp->success;
			$recaptcha_errors = $resp->errorCodes;
		}
		if ( ! $recaptcha_valid ) {
			$pmpro_msg  = sprintf( __( "reCAPTCHA failed. (%s) Please try again.", 'paid-memberships-pro' ), $recaptcha_errors );
			$pmpro_msgt = "pmpro_error";
		} else {			
			pmpro_set_session_var( 'pmpro_recaptcha_validated', true );
		}
	}
	
    if (!empty($missing_billing_field)) {
        $pmpro_msg = __("Please complete all required fields.", 'paid-memberships-pro' );
        $pmpro_msgt = "pmpro_error";
    } elseif ($bemail != $bconfirmemail) {
        $pmpro_msg = __("Your email addresses do not match. Please try again.", 'paid-memberships-pro' );
        $pmpro_msgt = "pmpro_error";
    } elseif (!is_email($bemail)) {
        $pmpro_msg = __("The email address entered is in an invalid format. Please try again.", 'paid-memberships-pro' );
        $pmpro_msgt = "pmpro_error";
    } elseif ( $pmpro_msgt == 'pmpro_error' ) {
		// Something else threw an error, maybe reCAPTCHA.		
	} else {
        //all good. update billing info.
        $pmpro_msg = __("All good!", 'paid-memberships-pro' );

        $pmpro_billing_order->cardtype = $CardType;
        $pmpro_billing_order->accountnumber = $AccountNumber;
        $pmpro_billing_order->expirationmonth = $ExpirationMonth;
        $pmpro_billing_order->expirationyear = $ExpirationYear;
        $pmpro_billing_order->ExpirationDate = $ExpirationMonth . $ExpirationYear;
        $pmpro_billing_order->ExpirationDate_YdashM = $ExpirationYear . "-" . $ExpirationMonth;
        $pmpro_billing_order->CVV2 = $CVV;
        
        //not saving email in order table, but the sites need it
        $pmpro_billing_order->Email = $bemail;

        //sometimes we need these split up
        $pmpro_billing_order->FirstName = $bfirstname;
        $pmpro_billing_order->LastName = $blastname;
        $pmpro_billing_order->Address1 = $baddress1;
        $pmpro_billing_order->Address2 = $baddress2;

        //other values
        $pmpro_billing_order->billing->name = $bfirstname . " " . $blastname;
        $pmpro_billing_order->billing->street = trim($baddress1 . " " . $baddress2);
        $pmpro_billing_order->billing->city = $bcity;
        $pmpro_billing_order->billing->state = $bstate;
        $pmpro_billing_order->billing->country = $bcountry;
        $pmpro_billing_order->billing->zip = $bzipcode;
        $pmpro_billing_order->billing->phone = $bphone;

        //$gateway = pmpro_getOption("gateway");
        $pmpro_billing_order->gateway = $gateway;
        $pmpro_billing_order->setGateway();
        
        /**
         * Filter the order object.
         *
         * @since 1.8.13.2
         *
         * @param object $order the order object used to update billing			 
         */
        $pmpro_billing_order = apply_filters( "pmpro_billing_order", $pmpro_billing_order );

        if ( $pmpro_billing_order->updateBilling() ) {
            //send email to member
            $pmproemail = new PMProEmail();
            $pmproemail->sendBillingEmail($current_user, $pmpro_billing_order);

            //send email to admin
            $pmproemail = new PMProEmail();
            $pmproemail->sendBillingAdminEmail($current_user, $pmpro_billing_order);

            //update the user meta too
            $meta_keys = array("pmpro_bfirstname", "pmpro_blastname", "pmpro_baddress1", "pmpro_baddress2", "pmpro_bcity", "pmpro_bstate", "pmpro_bzipcode", "pmpro_bcountry", "pmpro_bphone", "pmpro_bemail", "pmpro_CardType", "pmpro_AccountNumber", "pmpro_ExpirationMonth", "pmpro_ExpirationYear");
            $meta_values = array($bfirstname, $blastname, $baddress1, $baddress2, $bcity, $bstate, $bzipcode, $bcountry, $bphone, $bemail, $CardType, hideCardNumber($AccountNumber), $ExpirationMonth, $ExpirationYear);
            pmpro_replaceUserMeta($current_user->ID, $meta_keys, $meta_values);

            //message
            $pmpro_msg = sprintf(__('Information updated. <a href="%s">&laquo; back to my account</a>', 'paid-memberships-pro' ), pmpro_url("account"));
            $pmpro_msgt = "pmpro_success";
			
			do_action( 'pmpro_after_update_billing', $current_user->ID, $pmpro_billing_order );
        } else {
			/**
			 * Allow running code when the update fails.
			 *
			 * @since 2.7
			 * @param MemberOrder $pmpro_billing_order The order for the sub being updated.
			 */
			do_action( 'pmpro_update_billing_failed', $pmpro_billing_order );
			
			// Make sure we have an error message.
			$pmpro_msg = $pmpro_billing_order->error;

            if (!$pmpro_msg)
                $pmpro_msg = __("Error updating billing information.", 'paid-memberships-pro' );
            $pmpro_msgt = "pmpro_error";
        }
    }
} else {
    //default values from DB
    $bfirstname = get_user_meta($current_user->ID, "pmpro_bfirstname", true);
    $blastname = get_user_meta($current_user->ID, "pmpro_blastname", true);
    $baddress1 = get_user_meta($current_user->ID, "pmpro_baddress1", true);
    $baddress2 = get_user_meta($current_user->ID, "pmpro_baddress2", true);
    $bcity = get_user_meta($current_user->ID, "pmpro_bcity", true);
    $bstate = get_user_meta($current_user->ID, "pmpro_bstate", true);
    $bzipcode = get_user_meta($current_user->ID, "pmpro_bzipcode", true);
    $bcountry = get_user_meta($current_user->ID, "pmpro_bcountry", true);
    $bphone = get_user_meta($current_user->ID, "pmpro_bphone", true);
    $bemail = get_user_meta($current_user->ID, "pmpro_bemail", true);
    $bconfirmemail = get_user_meta($current_user->ID, "pmpro_bemail", true);
    $CardType = get_user_meta($current_user->ID, "pmpro_CardType", true);
    //$AccountNumber = hideCardNumber(get_user_meta($current_user->ID, "pmpro_AccountNumber", true), false);
    $ExpirationMonth = get_user_meta($current_user->ID, "pmpro_ExpirationMonth", true);
    $ExpirationYear = get_user_meta($current_user->ID, "pmpro_ExpirationYear", true);
}

/**
 * Hook to run actions after the billing page preheader has loaded.
 * @since 2.1
 */
do_action( 'pmpro_billing_after_preheader', $pmpro_billing_order );
