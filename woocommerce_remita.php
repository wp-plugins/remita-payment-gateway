<?php
/**
 * Plugin Name: Remita WooCommerce Payment Gateway
 * Plugin URI:  https://www.remita.net
 * Description: Remita Woocommerce Payment gateway allows you to accept payment on your Woocommerce store via Visa Cards, Mastercards, Verve Cards, eTranzact, PocketMoni, Paga, Internet Banking, Bank Branch and Remita Account Transfer.
 * Author:      Oshadami Mike
  * Version:     1.0
 */
add_filter('plugins_loaded', 'wc_remita_init' );
function wc_remita_init() {
	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}
	
	class WC_Remita extends WC_Payment_Gateway {	
			
		public function __construct() { 
			global $woocommerce;
			
			$this->id		= 'remita';
			$this->icon 	= apply_filters('woocommerce_remita_icon', plugins_url( 'remita-payment-options.png' , __FILE__ ) );
			$this->method_title     = __( 'Remita', 'woocommerce' );
		//	$this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Remita', home_url( '/' ) ) );
			$this->notify_url   = WC()->api_request_url('WC_Remita');
				$default_payment_type_options = array(
											'REMITA_PAY' => "Remita Account Transfer",  
											'Interswitch' => "Verve Card",  
											'UPL' => "Visa",  
											'MasterCard' => "MasterCard",  
											'PocketMoni' => "PocketMoni",
											'BANK_BRANCH' => "Bank Branch",
											'BANK_INTERNET' => "Internet Banking",
											'POS' => "POS",
											'ATM' =>"ATM"
										//Add more static Payment option here...
											);
												
			$this->payment_type_options = apply_filters( 'woocommerce_remita_card_types', $default_payment_type_options );
			// Load the form fields.
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
			
			// Define user set variables
			$this->title 		= $this->settings['title'];
			$this->description 	= $this->settings['description'];
			$this->mert_id 	= $this->settings['merchantid'];
			$this->serv_id 	= $this->settings['servicetypeid'];
			$this->api_key 	= $this->settings['apikey'];
			$this->mode		= $this->settings['mode'];
			$this->notificationkey	= $this->settings['notificationkey'];	
			 if($this->settings['notificationkey']!=null){
				$this->notificationkey = $this->settings['notificationkey'];
			   }
			   else {
				$this->settings['notificationkey'] = sha1(uniqid(mt_rand(), 1));				
			   }
			$this->settings['notificationurl'] = $this->notify_url.'?key='.$this->settings['notificationkey'];			   
			$cardtypes = $this->settings['remita_paymentoptions'];			
			$this->thanks_message	= $this->settings['thanks_message'];	
			$this->error_message	= $this->settings['error_message'];	
			$this->feedback_message	= '';
			$this->paymentTypes = $this->getEnabledPaymentTypes($cardtypes);
			add_action('woocommerce_receipt_remita', array(&$this, 'receipt_page'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
			add_action( 'woocommerce_checkout_update_order_meta', array(&$this,'my_ccustom_checkout_field_update_order_meta' ));	
			add_action('woocommerce_thankyou_' . $this->id, array(&$this, 'thankyou_page'));
			add_action( 'check_ipn_response', array( $this, 'check_ipn_response') ); 
			add_action( 'process_ipnb', array( $this, 'remita_notification') ); 
			// Payment listener/API hook
			add_action( 'woocommerce_api_wc_remita', array( &$this, 'process_ipn' )  );
			//Filters
			add_filter('woocommerce_currencies', array($this, 'add_ngn_currency'));
			add_filter('woocommerce_currency_symbol', array($this, 'add_ngn_currency_symbol'), 10, 2);
				
		}
	
		function my_ccustom_checkout_field_update_order_meta( $order_id ) {
							if ( ! empty( $_POST['paymenttype'] ) ) {
								update_post_meta( $order_id, 'paymentType', sanitize_text_field( $_POST['paymenttype'] ) );
							}
						}
				
		function getEnabledPaymentTypes($cardtypes)
				{
					$selected = $cardtypes;
								 foreach ($this->payment_type_options as $code=>$name) {
								if (!in_array($code,$selected)) {
									unset($this->payment_type_options[$code]);
								  }			
							}               
			 	return $this->payment_type_options;
				}
	    
		function add_ngn_currency($currencies) {
		     $currencies['NGN'] = __( 'Nigerian Naira (NGN)', 'woocommerce' );
		     return $currencies;
		}
		
		function add_ngn_currency_symbol($currency_symbol, $currency) {
			switch( $currency ) {
				case 'NGN':
					$currency_symbol = '₦';
					break;
			}
			
			return $currency_symbol;
		}    
	    
		function is_valid_for_use() {
			$return = true;
			
			if (!in_array(get_option('woocommerce_currency'), array('NGN'))) {
			    $return = false;
			}
		
			return $return;
		}
	    
			function admin_options() {
			$url=home_url( '/' );
			$url.="wp-content/plugins/woocommerce-remita/remita.png";
			echo '<h3>' . __('Remita Payment Gateway', 'woocommerce') . '</h3>';
			echo '<p>' . __('<br><img src="'.$url.'" border=0 />', 'woocommerce') . '</p>';
			echo '<table class="form-table">';
				
			if ( $this->is_valid_for_use() ) {
				$this->generate_settings_html();
			} else {
				echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', 'woocommerce' ) . '</strong>: ' . __( 'Remita does not support your store currency.', 'woocommerce' ) . '</p></div>';
			}
				
			echo '</table>';
				
		}
	    
	      
	       function init_form_fields() {
		 	   	  
		   	   $this->form_fields = array(
				'title' => array(
								'title' => __( 'Title', 'woocommerce' ), 
								'type' => 'text', 
								'default' => __( 'Remita Payment Gateway', 'woocommerce' ),
								'disabled' =>  true
							),
				'description' => array(
								'title' => __( 'Description', 'woocommerce' ), 
								'type' => 'textarea', 
								'disabled' =>  true,
								'default' => __("Pay Via Remita: Accepts Interswitch, Mastercard, Verve cards, eTranzact and Visa cards;", 'woocommerce')
							),
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'woocommerce' ), 
								'type' => 'checkbox', 
								'label' => __( 'Enable', 'woocommerce' ), 
								'default' => 'yes'
							), 
				'merchantid' => array(
								'title' => __( 'Merchant ID', 'woocommerce' ), 
								'type' => 'text' 
																
							),
				'servicetypeid' => array(
								'title' => __( 'Service Type ID', 'woocommerce' ), 
								'type' => 'text',
															
							),
				'apikey' => array(
								'title' => __( 'API Key', 'woocommerce' ), 
								'type' => 'text' 
																
							),
					'mode' => array(
							'title' => __( 'Environment', 'woocommerce' ),
							'type' 			=> 'select',
							'description' => __( 'Select Test or Live modes.', 'woothemes' ),
							'desc_tip'      => true,
							'placeholder'	=> '',
							'options'	=> array(
									'Test'=>"Test",
									'Live'=>"Live",
							
								)
						),
						'remita_paymentoptions' => array(
							'title' => __( 'Payment Options', 'woocommerce' ),
							'type' 			=> 'multiselect',
							'default' => 'Interswitch',
							'desc_tip'      => true,
							'description' => __( 'Select which Payment Channel to accept.', 'woothemes' ),
							'placeholder'	=> '',
							'options' => $this->payment_type_options,
						),
						'notificationkey' => array(
								'title' => __( 'Key', 'woocommerce' ), 
								'type' => 'text',
								'desc_tip'      => true,
								'description' => __( 'Make this long and hard to guess.', 'woothemes' ),
								'default' => $initKey								
																
							),
						'notificationurl' => array(
								'title' => __( 'Notification URL'), 
								'desc_tip'      => true,
								'description' => __( 'Copy The Notification URL and Paste in your Remita Profile.', 'woothemes' ),
								'type' => 'textarea',
								'disabled' =>  true,
								 							
							),
			
				);
	    
		}
	    
		function payment_fields() {
		 	// Description of payment method from settings
          		if ( $this->description ) { ?>
            		<p><?php echo $this->description; ?></p>
			<?php } ?>
				<fieldset>
				 <li class="payment_method_cod">
						Payment Type
						<label for="payment_method_cod">
							<select name="paymenttype" id="paymenttype" class="woocommerce-select">
								<option>-- Select Payment Type --</option>
								<?php  foreach( $this->paymentTypes as $key => $value ) { ?>
									<option value="<?php echo $key ?>"><?php _e( $value, 'woocommerce' ); ?></option>
								<?php } ?>
							 </select>		
						 </label>		
					</li>								 
				</fieldset>
			            			
		<?php  } 
			
			
		function get_remita_args( $order ) {
			global $woocommerce;
			$order_id = $order->id;
			$redirect_url = $this -> notify_url;
			$order_total = round(((number_format($this->get_order_total($order) + $woocommerce->cart->get_total_discount(), 2, '.', ''))),0);
			$hash_string = $this ->mert_id . $this ->serv_id . $order_id . $order_total . $redirect_url . $this ->api_key;
			$hash = hash('sha512', $hash_string);
			$cardType	= get_post_meta( $order->id, 'paymentType', true );
			$remita_args = array(
				'merchantId' => $this -> mert_id,
				'serviceTypeId' => $this -> serv_id,
				'amt' => $order_total,
				'hash' => $hash,
				'orderId' => $order_id,
				'responseurl' => $redirect_url,
			    'paymenttype' => $cardType,
				'payerName' => trim($order->billing_first_name . ' ' . $order->billing_last_name),
				'payerEmail' => trim($order->billing_email),
				'payerPhone' => trim($order->billing_phone),
			);
			
			if (isset($order->user_id)) {
				$remita_args['cust_id'] = $order->user_id;
			}
			
			$remita_args = apply_filters('woocommerce_remita_args', $remita_args);
			
			return $remita_args;
		}
	
		function generate_remita_form( $order_id ) {
			global $woocommerce;
			
			$order = new WC_Order( $order_id );
			$remita_args = $this->get_remita_args( $order );
			$remita_args_array = array();
			if( $this->mode == 'Test' ){
			$gateway_url = 'http://www.remitademo.net/remita/ecomm/init.reg';
			}
			else if( $this->mode == 'Live' ){
				$gateway_url = 'https://login.remita.net/remita/ecomm/init.reg';
			}
		foreach ($remita_args as $key => $value) {
				$remita_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
			}
			//
				wc_enqueue_js('
					jQuery("body").block({
							message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Remita.', 'woothemes').'",
							overlayCSS:
							{
								background: "#fff",
								opacity: 0.6
							},
							css: {
						        padding:        20,
						        textAlign:      "center",
						        color:          "#555",
						        border:         "3px solid #aaa",
						        backgroundColor:"#fff",
						        cursor:         "wait",
						        lineHeight:		"32px"
						    }
						});
					jQuery("#submit_remita_payment_form").click();
				');
				
	
			return '<form action="'.esc_url( $gateway_url ).'" method="post" id="remita_payment_form">
					' . implode('', $remita_args_array) . '
						<input type="submit" class="button alt" id="submit_remita_payment_form" value="'.__('Submit', 'woothemes').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
					</form>';
	
		}
		
		public function process_ipn(){
			@ob_clean();
    	if ( isset( $_GET['orderID'] )) {
    		do_action( 'check_ipn_response',$_GET );
    	}
    	if ( isset( $_GET['key'] ) ) {
    	if($_GET['key'] == $this->notificationkey){
		do_action( 'process_ipnb',$_POST );
		}
    	}
    }
    
		public function updatePaymentStatus($order,$response_code,$response_reason,$rrr)	{
			switch($response_code)
					{
				case "01":                    
					 if($order->status == 'processing'){
						$order->add_order_note('Payment Via Remita<br />Remita Retrieval Reference: '.$rrr);
						//Add customer order note
						$order->add_order_note('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Remita Retrieval Reference: '.$rrr, 1);
						// Reduce stock levels
						$order->reduce_order_stock();
						// Empty cart
						WC()->cart->empty_cart();
						$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.<br />Remita Retrieval Reference: '.$rrr;
						$message_type = 'success';
					}
					else{
						if( $order->has_downloadable_item() ){
							//Update order status
							$order->update_status( 'completed', 'Payment received, your order is now complete.' );
							$order->add_order_note('Payment Received.<br />Your order is now complete.<br />Remita Retrieval Reference: '.$rrr);
							$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.<br />Remita Retrieval Reference: '.$rrr;
							$message_type = 'success';
						}
						else{
							//Update order status
							$order->update_status( 'processing', 'Payment received, your order is currently being processed.' );
							$order->add_order_note('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Remita Retrieval Reference: '.$rrr);
							$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.<br />Remita Retrieval Reference: '.$rrr;
							$message_type = 'success';
						}
						// Reduce stock levels
						$order->reduce_order_stock();
						// Empty cart
						WC()->cart->empty_cart();
						}
					break;
					case "021":  
					$message = 	'Thank you for shopping with us. <br />RRR Generated Successfully <br />You can make payment for the RRR by visiting the nearest ATM or POS.<br />Remita Retrieval Reference: '.$rrr;
					$message_type = 'success';
					//Add Admin Order Note
					$order->add_order_note( $message );
					//Update the order status
					break;
					default:
						//process a failed transaction
						$message = 	'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.<br />Reason: '. $response_reason.'<br />Remita Retrieval Reference: '.$rrr;
						$message_type = 'error';
						$order->add_order_note( $message );
						//Update the order status
						$order->update_status('failed', '');
						break;
				}	
				return array($message, $message_type);
				}
		/**
         * Remita Payment Notification
         */
		public function remita_notification($posted) {
		$json = file_get_contents('php://input');
		$arr=json_decode($json,true);
		try {
		if($arr!=null){
			foreach($arr as $key => $orderArray){
				$orderRef = $orderArray['orderRef'];			
				$response =  $this->remita_transaction_details($orderRef);
				$response_code = $response['status'];
				$rrr = $response['RRR'];
				$response_reason = $response['message'];
				$orderId = $response['orderId'];
				$order = new WC_Order( (int) $orderId );
				$callUpdate = $this->updatePaymentStatus($order,$response_code,$response_reason,$rrr);	
				}
	
		}
		exit('OK');
		}
		catch (Exception $e) {
				exit('Error Updating Notification: ' . $e);
			}
		
	}
		 /**
         * Confirm Remita transaction
         */
  
		function check_ipn_response($posted) {
		@ob_clean();
		global $woocommerce;
		if( isset($posted['orderID'] ) ){
		$orderId = $posted['orderID'];
		$order_id 		= $orderId;
		$response = $this->remita_transaction_details($order_id);
		$response_code = $response['status'];
		$rrr = $response['RRR'];
		$response_reason = $response['message'];
		$order = new WC_Order( (int) $order_id );
		$callUpdate = $this->updatePaymentStatus($order,$response_code,$response_reason,$rrr);
		$message = $callUpdate[0];
		$message_type = $callUpdate[1]; 		
			}
		else {
			$message = 	'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.';
			$message_type = 'error';
			
			}
		wc_add_notice( $message, $message_type );
		$redirect_url = $this->get_return_url( $order );
        wp_redirect( $redirect_url );
        exit;		
		}
/**
	 	* Query a transaction details
	 	**/
			
		function remita_transaction_details($orderId){
			$mert =  $this -> mert_id;
			$api_key =  $this -> api_key;
			$hash_string = $orderId . $api_key . $mert;
			$hash = hash('sha512', $hash_string);
			if( $this->mode == 'Test' ){
			$query_url = 'http://www.remitademo.net/remita/ecomm';
			}
			else if( $this->mode == 'Live' ){
				$query_url = 'https://login.remita.net/remita/ecomm';
			}
			$url 	= $query_url . '/' . $mert  . '/' . $orderId . '/' . $hash . '/' . 'orderstatus.reg';
			//  Initiate curl
			$ch = curl_init();
			// Disable SSL verification
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			// Will return the response, if false it print the response
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			// Set the url
			curl_setopt($ch, CURLOPT_URL,$url);
			// Execute
			$result=curl_exec($ch);
			// Closing
			curl_close($ch);
			$response = json_decode($result, true);
			return $response;
		}
				 
		function thankyou_page() {
			echo wpautop($this->feedback_message);
		}
	
		
		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			$this->paymenttype 	= get_post_meta( $order_id, 'paymenttype', true );
				return array(
				'result' => 'success',
				'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
			);
		}
		
		function receipt_page( $order ) {
			echo '<p>'.__('Thank you for your order, please click the button below to pay with Remita.', 'woocommerce').'</p>';
			
			echo $this->generate_remita_form( $order );
		}
		
		 	}
		function woocommerce_add_remita_gateway( $methods ) {
		$methods[] = 'wc_remita'; 
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_remita_gateway' );
	
}

?>