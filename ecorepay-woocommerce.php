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


add_action( 'plugins_loaded', 'init_ecorepay_gateway_class' );
//add_action( 'plugins_loaded', 'le_pot_commun_load_textdomain' );
function init_ecorepay_gateway_class()
{
    class WC_Gateway_Ecorepay extends WC_Payment_Gateway
    {
        const GATEWAY_URL = 'https://gateway.ecorepay.cc';

        function __construct()
        {

            $this->id = 'ecorepay_payment';
            $this->has_fields = true;
            $this->method_title = __('Ecorepay gatewy' , 'ecorepay-woocommerce') ;
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
            $xmlquerybuild  = '<?xml version="1.0" encoding="utf-8"?>
            <Request type="'.$action.'">
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
            <DOB>’.$dob.’</DOB>
            <SSN>’.$ssn.’</SSN>
            <Address>'.$address.'</Address>
            <City>'.$city.'</City>
            <State>'.$state.'</State>
            <PostCode>'.$postcode.'</PostCode>
            <Country>'.$countrycode.'</Country>
            <CardNumber>'.$card_no.'</CardNumber>
            <CardExpMonth>'.$card_exp_month.'</CardExpMonth>
            <CardExpYear>'.$card_exp_year.'</CardExpYear>
            <CardCVV>'.$card_cvv.'</CardCVV>
            <field1>’.$field1.’</field1>
            </Transaction>
            </Request>';
            return $xmlquerybuild;
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
            return $xmlresult;
        }

        function process_payment( $order_id )
        {
            global $woocommerce;
            $order = new WC_Order( $order_id );



            // Return thankyou redirect
            return array(
                'result'  => 'success',
                'redirect'=> $response['paymentPageUrl']
            );
            //if fail

        }


        /*
        Declare form fields
        */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled'                                    => array(
                    'title'  => __( 'Enable/Disable', 'woocommerce' ),
                    'type'   => 'checkbox',
                    'label'  => __( 'Enable', 'woocommerce' ),
                    'default'=> 'yes'
                ),
                'testmode'                             => array(
                    'title'  => __( 'Test Mode', 'woocommerce' ),
                    'type'   => 'checkbox',
                    'label'  => __( 'Enable', 'woocommerce' ),
                    'default'=> 'yes'
                ),
                'title'                                                  => array(
                    'title'      => __( 'Title', 'woocommerce' ),
                    'type'       => 'text',
                    'description'=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'    => __( '', 'woocommerce' ),
                    'desc_tip'   => true,
                ),
                'description'        => array(
                    'title'  => __( 'Customer Message', 'woocommerce' ),
                    'type'   => 'textarea',
                    'default'=> ''
                ),
                'merchant_id'        => array(
                    'title'      => __( 'Merchant ID', 'le-pot-commun-woocommerce' ),
                    'type'       => 'text',
                    'description'=> __( '', 'woocommerce' ),
                    'default'    => __( '', 'woocommerce' ),
                    'desc_tip'   => true,
                ),
                'merchant_key' => array(
                    'title'      => __( 'Merchant Key', 'le-pot-commun-woocommerce' ),
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
