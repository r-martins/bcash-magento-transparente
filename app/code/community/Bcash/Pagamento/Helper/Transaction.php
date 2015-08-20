<?php

require_once(Mage::getBaseDir("lib") . "/BcashApi/autoloader.php" );

use Bcash\Service\Consultation;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;

class Bcash_Pagamento_Helper_Transaction
{
	private $email;
	private $token;
	private $obj;

	public function __construct() {
		$this->obj = Mage::getSingleton('Bcash_Pagamento_Model_PaymentMethod');
		$this->email = $this->obj->getConfigData('email');
		$this->token = $this->obj->getConfigData('token');
	}

	public function getTransactionStatus($transactionId = null, $orderId = null) {

		$statusTransaction = 0;
		$consultation = new Consultation($this->email, $this->token);

		try {
			if(!empty($transactionId) && !is_null($transactionId)) {
				//Consulta pelo id da transação
				$response = $consultation->searchByTransaction($transactionId);
			}else if(!empty($orderId) && !is_null($orderId)) {
				//Consulta pelo id do pedido		
				$response = $consultation->searchByOrder($orderid);
			}

			if($response != null) {
				$statusTransaction = $response->some_name_tostatus();
			}			

		} catch (ValidationException $e) {
			Mage::getSingleton('core/session')->addError('Error:' . $e->getMessage());
		    Mage::log($e->getMessage() . " :: " . $e->getErrors());	

		} catch (ConnectionException $e) {
			Mage::getSingleton('core/session')->addError('Error:' . $e->getMessage());
		    Mage::log($e->getMessage() . " :: " . $e->getErrors());	
		}

		return $statusTransaction;
	}

}