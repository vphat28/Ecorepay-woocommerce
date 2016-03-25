<?php
/**
* Plugin Name: Ecorepay Woocommerce
* Plugin URI: https://sundaysea.com/
* Description: Add Ecorepay payment method to WooCommerce.
* Version: 1.0
* Author: Xavi Nguyen
* Author URI:
* License GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

define ('ECOREPAY_WC_PLUGIN',dirname(__FILE__));

add_action( 'plugins_loaded', 'ecorepay_load_textdomain' ); 
 
function ecorepay_load_textdomain() {
  $lang = load_plugin_textdomain( 'ecorepay-woocommerce', false,  basename( ECOREPAY_WC_PLUGIN ) . '/languages/' );
   
}

add_action( 'plugins_loaded', 'init_ecorepay_gateway_class' );
//add_action( 'plugins_loaded', 'le_pot_commun_load_textdomain' );
function init_ecorepay_gateway_class()
{
    class WC_Gateway_Ecorepay extends WC_Payment_Gateway
    {
        const GATEWAY_URL = 'https://gateway.ecorepay.cc';
        protected $order = null;
        function __construct()
        {
            $this->supports = array(
                'default_credit_card_form','products'
            );
            $this->id = 'ecorepay_payment';
            $this->has_fields = true;
            $this->method_title = __('Ecorepay gateway' , 'ecorepay-woocommerce') ;
            $this->method_description = __('Add Ecorepay payment to WooCommerce.', 'ecorepay-woocommerce');
            $this->init_form_fields();
            $this->init_settings();
            $this->currency = get_woocommerce_currency();
            $this->description = $this->get_option( 'description' );
            $this->title = $this->get_option( 'title' );
            $this->public_mode = $this->get_option( 'public_mode' );



            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this,'process_admin_options' ) );

        }

        

        private function build_authorizecapture_xml_query($requestArray)
        {
            extract($requestArray);

            //handle xml
            $xmlquerybuild = '<?xml version="1.0" encoding="utf-8"?>
            <Request type="AuthorizeCapture">
            <AccountID>'.$accountid.'</AccountID>
            <AccountAuth>'.$authcode.'</AccountAuth>
            <Transaction>
            <Reference>'.$reference.'</Reference>
            <Amount>'.$amount.'</Amount>
            <Currency>'.$currency.'</Currency>
            <Email>'.$email.'</Email>
            <IPAddress>'.$uip.'</IPAddress>
            <Phone>'.$phone.'</Phone>
            <FirstName>'.$firstname.'</FirstName>
            <LastName>'.$lastname.'</LastName>
            <DOB>'.$dob.'</DOB>
            <SSN>'.$ssn.'</SSN>
            <Address>'.$address.'</Address>
            <City>'.$city.'</City>
            <State>'.$state.'</State>
            <PostCode>'.$postcode.'</PostCode>
            <Country>'.$countrycode.'</Country>
            <CardNumber>'.$card_no.'</CardNumber>
            <CardExpMonth>'.$card_exp_month.'</CardExpMonth>
            <CardExpYear>'.$card_exp_year.'</CardExpYear>
            <CardCVV>'.$card_cvv.'</CardCVV>
            <field1>'.$field1.'</field1>
            </Transaction>
            </Request>';
            return $xmlquerybuild;


        }


        protected function order_complete()
        {

            if ( $this->order->status == 'completed' ) {
                return;
            }

            $this->order->payment_complete( $this->transaction_id );

            $this->order->add_order_note(
                sprintf(
                    __( '%s payment completed with Transaction Id of "%s"', 'ecorepay-woocommerce' ),
                    get_class( $this ),
                    $this->transaction_id
                )
            );
        }

        private function curl_request($xmlquerybuild)
        {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::GATEWAY_URL);
            curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlquerybuild);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $result    = curl_exec($ch);
            curl_close($ch);
            $xmlresult = simplexml_load_string($result);
            // Simple display of the end result
            $msg       = '';
            if (isset($xmlresult->ResponseCode)) {
                $responsecode = $xmlresult->ResponseCode;
            }
            if (isset($xmlresult->Description)) {
                $msg = $xmlresult->Description;
            }
            if (isset($xmlresult->Reference)) {
                $reference = $xmlresult->Reference;
            }
            if (isset($xmlresult->TransactionID)) {
                $this->transaction_id = (string)$xmlresult->TransactionID;
            }
            if (isset($xmlresult->ProcessingTime)) {
                $processingtime = $xmlresult->ProcessingTime;
            }
            if (isset($xmlresult->StatusCode)) {
                $statuscode = $xmlresult->StatusCode;
            }
            if (isset($xmlresult->StatusDescription)) {
                $statusdescription = $xmlresult->StatusDescription;
            }
            if (isset($responsecode)) {
                if ($responsecode != '100') {
                    $status = "ERROR";  ;
                }
                if ($responsecode == '100') {
                    $status = "SUCCESS"; ;
                }
            }
            else
            {
                $status = "ERROR";
            }
            return array ('status' => $status,'message'=> (string)$msg);

        }
        protected function get_order_total()
        {

            $total    = 0;
            $order_id = absint( get_query_var( 'order-pay' ) );

            // Gets order total from "pay for order" page.
            if ( 0 < $order_id ) {
                $order = wc_get_order( $order_id );
                $total = (float) $order->get_total();

                // Gets order total from cart / checkout.
            }
            elseif ( 0 < WC()->cart->total ) {
                $total = (float) WC()->cart->total;
            }

            return $total;
        }
        function send_to_ecorepay( $order_id )
        {
            $cardinfo = explode('/',$_POST['ecorepay_payment-card-expiry']);

            if (empty($_POST['billing_state']))
            $_POST['billing_state'] = $_POST['billing_city'];

            $requestArray = array(
                'accountid'     => $this->get_option('auth_id'),
                'reference'     => 'order id ' . $order_id ,
                'amount'        => 'order id ' . $order_id ,
                'authcode'      => $this->get_option('auth_code'),
                'amount'        => $this->get_order_total() ,
                'currency'      => $this->order->order_currency,
                'email'         => $this->order->billing_email,
                'phone'         => sanitize_text_field($_POST['billing_phone']),
                'uip'           => '127.0.0.1',
                'firstname'     => sanitize_text_field($_POST['billing_first_name']),
                'lastname'      => sanitize_text_field($_POST['billing_last_name']),
                'dob'           => '19810530',//hard coded now
                'ssn'=> '19810530',//hard coded now
                'address'=> sanitize_text_field($_POST['billing_address_1']),
                'city'          => sanitize_text_field($_POST['billing_city']),
                'state'         => sanitize_text_field($_POST['billing_state']),
                'postcode'      => sanitize_text_field($_POST['billing_postcode']),
                'countrycode'   => sanitize_text_field($_POST['billing_country']),
                'field1'        => $order_id,



                'card_no'       => str_replace(' ','', $_POST['ecorepay_payment-card-number']),
                'card_exp_month'=> trim($cardinfo[0]),
                'card_exp_year' => '20' . trim($cardinfo[1]),
                'card_cvv'      => $_POST['ecorepay_payment-card-cvc'],
            );
            $result = $this->curl_request($this->build_authorizecapture_xml_query($requestArray));
            RETURN $result;
        }

        function process_payment( $order_id )
        {

            global  $woocommerce;
            $this->order = new WC_Order( $order_id );
            $result = $this->send_to_ecorepay( $order_id );


            if ( $result['status'] == 'SUCCESS' )
            {
                $woocommerce->cart->empty_cart();
                $this->order_complete();

                $result = array(
                    'result'  => 'success',
                    'redirect'=> $this->get_return_url( $this->order )
                );

                return $result;
            }
            else
            {
                $this->payment_failed($result['message']);

                // Add a generic error message if we don't currently have any others
                if ( wc_notice_count( 'error' ) == 0 )
                {
                    wc_add_notice( __( $result['message'] , 'ecorepay-woocommerce' ), 'error' );
                }
            }
        }

        protected function payment_failed($message = '')
        {
            $this->order->add_order_note(
                sprintf(
                    __( '%s payment failed with message: "%s"', 'ecorepay-woocommerce' ),
                    get_class( $this ),
                    $message
                )
            );
        }


        /*
        Declare form fields
        */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled'                                                                                     => array(
                    'title'  => __( 'Enable/Disable', 'woocommerce' ),
                    'type'   => 'checkbox',
                    'label'  => __( 'Enable', 'woocommerce' ),
                    'default'=> 'yes'
                ),
                'testmode'                                                                   => array(
                    'title'  => __( 'Test Mode', 'woocommerce' ),
                    'type'   => 'checkbox',
                    'label'  => __( 'Enable', 'woocommerce' ),
                    'default'=> 'yes'
                ),
                'title'                                                                                                                         => array(
                    'title'      => __( 'Title', 'woocommerce' ),
                    'type'       => 'text',
                    'description'=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'    => __( '', 'woocommerce' ),
                    'desc_tip'   => true,
                ),
                'description'             => array(
                    'title'  => __( 'Customer Message', 'woocommerce' ),
                    'type'   => 'textarea',
                    'default'=> ''
                ),
                'auth_id'                                     => array(
                    'title'      => __( 'Account ID', 'ecorepay-woocommerce' ),
                    'type'       => 'text',
                    'description'=> __( '', 'woocommerce' ),
                    'default'    => __( '', 'woocommerce' ),
                    'desc_tip'   => true,
                ),
                'auth_code'             => array(
                    'title'      => __( 'Authorisation Code', 'ecorepay-woocommerce' ),
                    'type'       => 'text',
                    'description'=> __( '', 'woocommerce' ),
                    'default'    => __( '', 'woocommerce' ),
                    'desc_tip'   => true,
                )
            );
        }
    }
}



function add_ecorepay_gateway_class( $methods )
{
    $methods[] = 'WC_Gateway_Ecorepay';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_ecorepay_gateway_class' );
