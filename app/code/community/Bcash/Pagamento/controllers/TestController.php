<?php

class Bcash_Pagamento_TestController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
		$pagamentoDataHelper = Mage::helper('pagamento');

    	//echo Mage::helper('pagamento')->getTransaction();

    	// TODO: Código de transação do pedido
    	//$orderTransaction = $newOrderData['some_attribute_value'];
		$orderTransaction = "232";

		//Mage::log($observer->getEvent()->getPayment()->getMethodInstance()->getCode());	
		//Mage::log($observer->getEvent()->getTransaction());
		if(!is_null($orderTransaction) && !empty($orderTransaction))
		{
			//Mage::model();
			//$pagamentoOrderModel = Mage::getModel('pagamento/order');
			//$return = $pagamentoOrderModel->cancellation($orderTransaction);

			$return = $pagamentoDataHelper->getTransaction($orderTransaction, null);
			echo "<pre> return by transaction code: ";
			print_r($return);
		}

		$orderId = "000000593";
		if(!is_null($orderId) && !empty($orderId))
		{
			$return = $pagamentoDataHelper->getTransaction(null, $orderId);
			echo "<pre> return by order id: ";
			print_r($return);
		}
    }		
}