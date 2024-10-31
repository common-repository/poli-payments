<?php
/**
 * Plugin Name: POLi Payments
 * Plugin URI: http://www.polipayments.com
 * Description: A payment gateway plugin for POLi Payments
 * Version: 1.4
 * Author: POLi Payments
 * Author URI: http://www.polipayments.com
 * License: GPL-3.0
 */


add_action('plugins_loaded', 'woocommerce_gateway_poli_init', 0);

function woocommerce_gateway_poli_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-gateway-poli', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

    /**
     * Gateway class
     */
    class WC_Gateway_POLi extends WC_Payment_Gateway {

        public function __construct() {

            $this->method_title = 'POLi Payments';
            $this->id = 'POLi';
            $this->title = 'POLi Payments';



            // Create admin configuration form
            $this->initForm();

            // Initialise gateway settings
            $this->init_settings();




            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_poli_nudge', array($this, 'result'));


            $this->description = 'Pay with POLi! You will be transferred to your internet banking.<br>'
                .'<a target="_blank" href="http://www.polipayments.com/consumer"><img src="'.plugins_url('', __FILE__) . '/images/poli.gif"></a>'
                .'<br><a target="_blank" href = https://transaction.apac.paywithpoli.com/POLiFISupported.aspx?merchantcode='.$this->get_option('merchantcode').'>Available Banks</a>';
        }









        public function result() {
            global $woocommerce;
            if(!empty($_POST["Token"])||!empty($_GET["token"])) {
                $token = $_POST["Token"];
                if(!$token){$token = $_GET["token"];}
                $xml_builder = '<?xml version="1.0" encoding="utf-8"?>
<GetTransactionRequest
xmlns="http://schemas.datacontract.org/2004/07/Centricom.POLi.Services.MerchantAPI.Contracts" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
<AuthenticationCode>'.$this->get_option('authenticationcode').'</AuthenticationCode>
<MerchantCode>'.$this->get_option('merchantcode').'</MerchantCode>
<TransactionToken>' . $token . '</TransactionToken>
</GetTransactionRequest>';


                $ch = curl_init('https://merchantapi.apac.paywithpoli.com/MerchantAPIService.svc/Xml/transaction/query');
                curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:  text/xml'));
                curl_setopt( $ch, CURLOPT_HEADER, 0);
                curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt( $ch, CURLOPT_POST, 1);
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml_builder);
                curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0);
                $referrer = "";
                curl_setopt($ch, CURLOPT_REFERER, $referrer);
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec( $ch );
                curl_close ($ch);


                $xml = new SimpleXMLElement($response);

                $xml->registerXPathNamespace('','http://schemas.datacontract.org/2004/07/Centricom.POLi.Services.MerchantAPI.Contracts');
                $xml->registerXPathNamespace('a','http://schemas.datacontract.org/2004/07/Centricom.POLi.Services.MerchantAPI.DCO');
                $xml->registerXPathNamespace('i','http://www.w3.org/2001/XMLSchema-instance');

                $orderid = 0;
                $status = "1";
                $order_status = "10";
                $poli_id = '';
				$completed = "";

                if($token){//If there's a token, then we would be recieving output from checking transaction. Not the best way, but it works

                    foreach($xml->xpath('//a:AmountPaid') as $value) {
                        echo '<br>Amount Paid: '.$value;
                    }
                    foreach($xml->xpath('//a:TransactionRefNo') as $value) {
                        echo '<br>POLi ID: '.$value;
                        $poli_id = (string)$value;
                    }
                    foreach($xml->xpath('//a:PaymentAmount') as $value) {
                        echo '<br>Payment Amount: '.$value;
                    }
                    foreach($xml->xpath('//a:CurrencyName') as $value) {
                        echo '<br>Currency Name: '.$value;
                    }
                    foreach($xml->xpath('//a:BankReceipt') as $value) {
                        echo '<br>Bank Receipt: '.$value;
                    }
                    foreach($xml->xpath('//a:BankReceiptDateTime') as $value) {
                        echo '<br>Bank Receipt Date/Time: '.$value;
                    }
                    foreach($xml->xpath('//a:FinancialInstitutionCode') as $value) {
                        echo '<br>Financial Institution Code: '.$value;
                    }
                    foreach($xml->xpath('//a:MerchantReference') as $value) {
                        echo '<br>Merchant Reference: '.$value;
                        $orderid = $value;
                    }
                    foreach($xml->xpath('//a:MerchantDefinedData') as $value) {
                        echo '<br>Merchant Defined Data: '.$value;
                        $order_status = $value;
                    }
                    foreach($xml->TransactionStatusCode as $value) {
                        echo 'Transaction Status Code: '.$value;
						$completed = $value;
                        if($value=="Completed"){
                            $status=$order_status;
                        }
                        if($value=="TimedOut"){
                            $status="10";
                        }
                        if($value=="Failed"){
                            $status="10";
                        }
                        if($value=="Cancelled"){
                            $status="10";
                        }
                    }
                }
				if($completed == "Completed"){
				
					if($orderid!=""||$orderid!=null){

						$order = &new WC_Order(''.$orderid);
						$order->add_order_note( __('POLi Nudge Recieved. POLi ID: '.$poli_id, 'woothemes') );
						$order->payment_complete();
					}

                }


            }
            if($_GET["token"]) {
                $woocommerce->cart->empty_cart();
                //$redirect_url = get_permalink(woocommerce_get_page_id('thanks' ));
                $redirect_url = $this->get_return_url( $order );
				wp_safe_redirect($redirect_url);

            }
            exit();

        }
        private function initForm() {
            $this->form_fields = array(
                'configuration' => array(
                    'title' => __('Set-up configuration', 'POLi'),
                    'type' => 'title'
                ),
                'enabled' => array(
                    'title' => __( 'Enable POLi', 'POLi' ),
                    'label' => __( 'Enable POLi Payments in the checkout', 'POLi' ),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'merchantcode' => array(
                    'title' => __('Merchant Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Please input your Merchant Code', 'POLi'),
                    'desc_tip' => true
                ),
                'authenticationcode' => array(
                    'title' => __('authenticationcode', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Please input your Authentication Code', 'POLi'),
                    'desc_tip' => true
                )
            );
        }


        function admin_options() {
            ?>
            <h3><?php _e('POLi Payments','POLi'); ?></h3>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <?php
        }

        function process_payment ($order_id) {
            global $woocommerce;

            $order = new WC_Order( $order_id );

            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('pending', __( 'Payment yet to be recieved', 'POLi Payments' ));



            $authenticationcode = $this->get_option('authenticationcode');
            $merchantcode = $this->get_option('merchantcode');
            $amount = $order->get_total();
            $currency = get_woocommerce_currency();
            $actuallink = ''.get_permalink(woocommerce_get_page_id('pay'));

            $datetime = date('Y-m-d').'T'.date('H:i:s');
            $ipaddress = $_SERVER["REMOTE_ADDR"];
            $baselink = ''.site_url();
            $merchantdata = $order_id;

            $nudge = ''.str_replace('https:', 'http:', add_query_arg('wc-api', 'poli_nudge', home_url('/')));
            $success = ''.str_replace('https:', 'http:', add_query_arg('wc-api', 'poli_nudge', home_url('/')));
            $cancel = ''.get_permalink(woocommerce_get_page_id('pay'));


            $url = "https://merchantapi.apac.paywithpoli.com/MerchantAPIService.svc/Xml/transaction/initiate";//Set url to the initiate endpoint. Check carefully.
            $xml_builder = '<?xml version="1.0" encoding="utf-8"?>
<InitiateTransactionRequest
xmlns="http://schemas.datacontract.org/2004/07/Centricom.POLi.Services.MerchantAPI.Contracts"
xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
	<AuthenticationCode>'.$authenticationcode.'</AuthenticationCode>
	<Transaction xmlns:dco="http://schemas.datacontract.org/2004/07/Centricom.POLi.Services.MerchantAPI.DCO">
		<dco:CurrencyAmount>'.$amount.'</dco:CurrencyAmount>
		<dco:CurrencyCode>'.$currency.'</dco:CurrencyCode>
		<dco:MerchantCheckoutURL>'.$actuallink.'</dco:MerchantCheckoutURL>
		<dco:MerchantCode>'.$merchantcode.'</dco:MerchantCode>
		<dco:MerchantData>Data</dco:MerchantData>
		<dco:MerchantDateTime>'.$datetime.'</dco:MerchantDateTime>
		<dco:MerchantHomePageURL>'.$baselink.'</dco:MerchantHomePageURL>
		<dco:MerchantRef>'.$merchantdata.'</dco:MerchantRef>
		<dco:MerchantReferenceFormat>0</dco:MerchantReferenceFormat>
		<dco:NotificationURL>'.$nudge.'</dco:NotificationURL>
		<dco:SelectedFICode i:nil="true" />
		<dco:SuccessfulURL>'.$success.'</dco:SuccessfulURL>
		<dco:Timeout>0</dco:Timeout>
		<dco:UnsuccessfulURL>'.$cancel.'</dco:UnsuccessfulURL>
		<dco:UserIPAddress>'.$ipaddress.'</dco:UserIPAddress>
  </Transaction>
</InitiateTransactionRequest>';//Set the xml builder to the correct xml for initiating transaction.
//Check it carefully. Date formatting, link formatting.

            $ch = curl_init($url);//Start a cURL on the url
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:  text/xml'));//Set the request type to xml.
            curl_setopt( $ch, CURLOPT_HEADER, 0);//Turn off debug info
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);//SSL related
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);//SSL related
            curl_setopt( $ch, CURLOPT_POST, 1);//Turn on post data
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml_builder);//Set the post data to the xml
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0);
            $referrer = "";
            curl_setopt($ch, CURLOPT_REFERER, $referrer);//Set the referrer
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec( $ch );//Execute cURL, save to response
            curl_close ($ch);//Save system resources by closing the cURL


            $xml = new SimpleXMLElement($response);//Save the response as XML

//$namespaces = $xml->getNamespaces(TRUE);
//var_dump($namespaces);

            $xml->registerXPathNamespace('','http://schemas.datacontract.org/2004/07/Centricom.POLi.Services.MerchantAPI.Contracts');
            $xml->registerXPathNamespace('a','http://schemas.datacontract.org/2004/07/Centricom.POLi.Services.MerchantAPI.DCO');//This is the important one.
            $xml->registerXPathNamespace('i','http://www.w3.org/2001/XMLSchema-instance');//These allow accessing of xpathing on the xml

            $redirect_url = '';
            $error_message=null;
            foreach($xml->xpath('//a:NavigateURL') as $value) {
                $redirect_url = ''.$value;
            }
            foreach($xml->xpath('///a:Code') as $value) {
                $error_message=$value;
            }


            if($error_message){
               $woocommerce->add_error(__('Payment error:', 'woothemes') . $error_message);
                return;
            }
















            // Reduce stock levels
            //$order->reduce_order_stock();

            // Remove cart
            //$woocommerce->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' 	=> 'success',
                'redirect'	=> $redirect_url
            );
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_gateway_poli_gateway($methods) {
        $methods[] = 'WC_Gateway_POLi';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_poli_gateway' );
    new WC_Gateway_POLi;
}