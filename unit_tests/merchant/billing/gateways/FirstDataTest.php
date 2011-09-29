<?php

/**
 * Description of FirstDataTest
 *
 * Usage:
 *   Navigate, from terminal, to folder where this files is located
 *   run phpunit FirstDataTest.php
 *
 * @package Aktive Merchant
 * @author  Dobie Gillis
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
require_once dirname(__FILE__) . '/../../../config.php';

class FirstDataTest extends PHPUnit_Framework_TestCase
{

	public $gateway;
	public $amount;
	public $options;
	public $creditcard;

	/**
	 * Setup
	 */
	function setUp()
	{
		Merchant_Billing_Base::mode('test');

		$login_info = array(
				'login' => FIRST_DATA_LOGIN,
				'pem' => FIRST_DATA_PEM);
		$this->gateway = new Merchant_Billing_FirstData($login_info);

		$this->amount = 100;
		$this->creditcard = new Merchant_Billing_CreditCard(array(
					"first_name" => "John",
					"last_name" => "Doe",
					"number" => "4111111111111111",
					"month" => "01",
					"year" => "2015",
					"verification_value" => "000"
					)
				);
		$this->options = array(
				'order_id' => 'REF' . $this->gateway->generate_unique_id(),
				'description' => 'First Data Test Transaction',
				'address' => array(
					'address1' => '1234 Street',
					'zip' => '98004',
					'state' => 'WA'
					)
				);

		$this->recurring_options = array(
				'amount' => 100,
				'subscription_name' => 'Test Subscription 1',
				'billing_address' => array(
					'first_name' => 'John' . $this->gateway->generate_unique_id(),
					'last_name' => 'Smith'
					),
				'length' => 11,
				'unit' => 'months',
				'start_date' => date("Y-m-d", time()),
				'occurrences' => 1
				);
	}

	/**
	 * Tests
	 */
	public function testSuccessfulPurchase()
	{
		$response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
		$this->assert_success($response);
		$this->assertTrue($response->test());
		$this->assertEquals('This is a test transaction and will not show up in the Reports', $response->message());
	}
	public function testSuccessfulAuthorization()
	{
		$response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
		$this->assert_success($response);
		$this->assertTrue($response->test());
		$this->assertEquals('This is a test transaction and will not show up in the Reports', $response->message());
	}

	public function testAuthorizationAndCapture()
	{
		$response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
		$this->assert_success($response);

		$authorization = $response->authorization();

		$capture = $this->gateway->capture($this->amount, $authorization, $this->options);
		$this->assert_success($capture);
		$this->assertEquals('This is a test transaction and will not show up in the Reports', $capture->message());
	}

	public function testExpiredCreditCard()
	{
		$this->creditcard->year = 2004;
		$response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
		$this->assertEquals('This is a test transaction and will not show up in the Reports', $response->message());
	}

	/**
	 * Private methods
	 */
	private function assert_success($response)
	{
		$this->assertTrue($response->success());
	}

}

?>
