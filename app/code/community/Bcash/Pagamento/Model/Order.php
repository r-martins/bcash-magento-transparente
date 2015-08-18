<?php

require_once(Mage::getBaseDir("lib") . "/BcashApi/autoloader.php" );

use Bcash_Pagamento_Helper_RegisterSdk;
use Bcash\Service\Cancellation;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

/**
 * 
 *
 */
class Bcash_Pagamento_Model_Order extends Mage_Core_Model_Abstract
{

	private $email;
	private $consumer_key;
	private $obj;

	public function __construct()
	{
		$this->obj = Mage::getSingleton('Bcash_Pagamento_Model_PaymentMethod');
		$this->email = $this->obj->getConfigData('email');
		$this->consumer_key = $this->obj->getConfigData('consumer_key');
	}

	public function cancellation($transactionId)
	{

		$cancellation = new Cancellation($this->email, $this->consumer_key);
		$cancellation->enableSandBox(true);

		try {
		    $transactionId = 186148; // Id da transacao bcash a ser cancelada
		    $response = $cancellation->execute($transactionId);
		    echo "<pre>";
		    var_dump($response);die;
		    echo "</pre>";

		} catch (ValidationException $e) {
		    echo "ValidationException: " . $e->getMessage() . "\n";
		    echo "<pre>";
		    var_dump($e->getErrors());die;
		    echo "</pre>";

		} catch (ConnectionException $e) {
		    echo "ConnectionException: " . $e->getMessage() . "\n";
		    echo "<pre>";
		    var_dump($e->getErrors());die;
		    echo "</pre>";
		}
	}
}