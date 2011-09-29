<?php
require_once dirname(__FILE__) . '/../../../support/lphp.php';

/**
 * Description of Merchant_Billing_FirstData
 *
 * @package Aktive Merchant
 * @author  Dobie Gillis
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Billing_FirstData extends Merchant_Billing_Gateway
{
	const TEST_URL = 'https://secure.linkpt.net:1129/';
	const LIVE_URL = 'https://secure.linkpt.net:1129/';

	# The countries the gateway supports merchants from as 2 digit ISO country codes

	public static $supported_countries = array('US');

	# The card types supported by the payment gateway
	public static $supported_cardtypes = array('visa', 'master', 'american_express', 'discover', 'jcb', 'diners_club');

	# The homepage URL of the gateway
	public static $homepage_url = 'http://www.firstdata.com';

	# The display name of the gateway
	public static $display_name = 'FirstData';
	private $options;
	private $post;

	/**
	 * $options array includes login parameters of merchant and optional currency.
	 *
	 * @param array $options
	 */
	public function __construct($options = array())
	{
		$this->required_options('login, pem', $options);

		if (isset($options['currency'])) {
			$this->default_currency = $options['currency'];
		}

		$this->options = $options;
	}

	/**
	 *
	 * @param float                       $money
	 * @param Merchant_Billing_CreditCard $creditcard
	 * @param array                       $options
	 *
	 * @return Merchant_Billing_Response
	 */
	public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options=array())
	{
		$this->add_invoice($options);
		$this->add_creditcard($creditcard);
		$this->add_address($options);
		$this->add_customer_data($options);

		return $this->commit('PREAUTH', $money);
	}

	/**
	 *
	 * @param number                      $money
	 * @param Merchant_Billing_CreditCard $creditcard
	 * @param array                       $options
	 *
	 * @return Merchant_Billing_Response
	 */
	public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options=array())
	{
		$this->add_invoice($options);
		$this->add_creditcard($creditcard);
		$this->add_address($options);
		$this->add_customer_data($options);

		return $this->commit('SALE', $money);
	}

	/**
	 *
	 * @param number $money
	 * @param string $authorization (unique value received from authorize action)
	 * @param array  $options
	 *
	 * @return Merchant_Billing_Response
	 */
	public function capture($money, $authorization, $options = array())
	{
		$this->post = array('authorization_id' => $authorization);
		$this->add_customer_data($options);

		return $this->commit('POSTAUTH', $money);
	}

	/**
	 *
	 * @param string $authorization
	 * @param array  $options
	 *
	 * @return Merchant_Billing_Response
	 */
	public function void($authorization, $options = array())
	{
		$this->post = array('authorization' => $authorization);
		return $this->commit('VOID', null);
	}

	/**
	 *
	 * @param number $money
	 * @param string $identification
	 * @param array  $options
	 *
	 * @return Merchant_Billing_Response
	 */
	public function credit($money, $identification, $options = array())
	{
		$this->post = array('authorization' => $identification);

		$this->add_invoice($options);
		return $this->commit('credit', $money);
	}

	/* Private */

	/**
	 * Customer data like e-mail, ip, web browser used for transaction etc
	 *
	 * @param array $options
	 */
	private function add_customer_data($options)
	{

	}

	/**
	 *
	 * Options key can be 'shipping address' and 'billing_address' or 'address'
	 * Each of these keys must have an address array like:
	 * <code>
	 * $address['name']
	 * $address['company']
	 * $address['address1']
	 * $address['address2']
	 * $address['city']
	 * $address['state']
	 * $address['country']
	 * $address['zip']
	 * $address['phone']
	 * </code>
	 * common pattern for address is
	 * <code>
	 * $billing_address = isset($options['billing_address']) ? $options['billing_address'] : $options['address']
	 * $shipping_address = $options['shipping_address']
	 * </code>
	 *
	 * @param array $options
	 */
	private function add_address($options)
	{
		$address = isset($options['billing_address']) ? $options['billing_address'] : $options['address'];

		$this->post['address1'] = isset($address['address1']) ? $address['address1'] : null;
		$this->post['company'] = isset($address['company']) ? $address['company'] : null;
		$this->post['phone'] = isset($address['phone']) ? $address['phone'] : null;
		$this->post['zip'] = isset($address['zip']) ? $address['zip'] : null;
		$this->post['city'] = isset($address['city']) ? $address['city'] : null;
		$this->post['country'] = isset($address['country']) ? $address['country'] : null;
		$this->post['state'] = isset($address['state']) ? $address['state'] : 'n/a';
	}

	/**
	 *
	 * @param array $options
	 */
	private function add_invoice($options)
	{

	}

	/**
	 *
	 * @param Merchant_Billing_CreditCard $creditcard
	 */
	private function add_creditcard(Merchant_Billing_CreditCard $creditcard)
	{
		$this->post['cardnumber'] = $creditcard->number;
		$this->post['cardexpmonth'] = $this->cc_format($creditcard->month, 'two_digits');
		$this->post['cardexpyear'] = $this->cc_format($creditcard->year, 'two_digits');
		$this->post['name'] = "{$creditcard->first_name} {$creditcard->last_name}";
		$this->post['cvmindicator'] = 'provided';
		$this->post['cvmvalue'] = $creditcard->verification_value;
	}

	/**
	 * Parse the raw data response from gateway
	 *
	 * @param string $body
	 */
	private function parse($response_xml)
	{
		$response_xml = '<?xml version="1.0" encoding="utf-8"?><document>'.$response_xml.'</document>';
		$xml = simplexml_load_string($response_xml);
		return $xml;
	}

	/**
	 *
	 * @param string $action
	 * @param number $money
	 * @param array  $parameters
	 *
	 * @return Merchant_Billing_Response
	 */
	private function commit($action, $money, $parameters=array())
	{
		$url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;

		if ($action != 'VOID') {
			$parameters['chargetotal'] = $money;
		}

		/* Request a test response */
		$parameters['result'] = $this->is_test() ? 'GOOD' : 'LIVE';

		$mylphp = new lphp;
		$post_data = $this->post_data($action, $parameters);
		$post_data = $mylphp->buildXML($post_data);

		$response = $this->parse($this->ssl_post($url, $post_data));

		$test_mode = $this->is_test();
		return new Merchant_Billing_Response(
			$this->success_from($response),
			$this->message_from($response),
			get_object_vars($response),
			array(
				'test' => $test_mode,
				'avs_result' => $this->avs_result_from($response),
				'cvv_result' => $response->r_avs
				)
			);
	}

	/**
	 * Returns success flag from gateway response
	 *
	 * @param array $response
	 *
	 * @return string
	 */
	private function success_from($response)
	{
		return $response->r_approved == 'APPROVED';
	}

	/**
	 * Returns message (error explanation  or success) from gateway response
	 *
	 * @param array $response
	 *
	 * @return string
	 */
	private function message_from($response)
	{
		return strip_tags($response->r_message->asXML());
	}

	/**
	 * Returns fraud review from gateway response
	 *
	 * @param array $response
	 *
	 * @return string
	 */
	private function fraud_review_from($response)
	{

	}

	/**
	 *
	 * Returns avs result from gateway response
	 *
	 * @param array $response
	 *
	 * @return string
	 */
	private function avs_result_from($response)
	{
		return $response->r_avs;
	}

	/**
	 *
	 * Add final parameters to post data and
	 * build $this->post to the format that your payment gateway understands
	 *
	 * @param string $action
	 * @param array  $parameters
	 */
	private function post_data($action, $parameters = array())
	{
		$this->post['host'] = $this->is_test() ? self::TEST_URL : self::LIVE_URL;
		$this->post['port'] = 1129;
		$this->post['configfile'] = $this->options['login'];
		$this->post['keyfile'] = $this->options['pem'];
		$this->post['ordertype'] = $action;

		$this->post = array_merge($this->post, $parameters);

		return $this->post;
	}

}

?>
