<?php

class Bcash_Pagamento_TestController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {

    	// TODO: Código de transação do pedido
    	//$orderTransaction = $newOrderData['some_attribute_value'];
		$orderTransaction = "a32ad4asdf435asdf435435as76"; 

		//Mage::log($observer->getEvent()->getPayment()->getMethodInstance()->getCode());	
		//Mage::log($observer->getEvent()->getTransaction());
		if(!is_null($orderTransaction) && !empty($orderTransaction))
		{
			//Mage::model();
			$pagamentoOrderModel = Mage::getModel('pagamento/order');
			$pagamentoOrderModel->cancellation($orderTransaction);
		}
    }		
}