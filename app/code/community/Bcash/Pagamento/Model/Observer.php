<?php

class Bcash_Pagamento_Model_Observer
{

	/**
	 * @param Varien_Event_Observer $observer
     */
	public function checkOrderState(Varien_Event_Observer $observer)
	{

		$origOrderData = $observer->getEvent()->getData('data_object')->getOrigData();
		$newOrderData = $observer->getEvent()->getData('data_object')->getData();

		Mage::log($origOrderData);

		echo '<pre>';
		print_r($observer->getEvent());
		echo '</pre>';
		if (($origOrderData['state'] !== $newOrderData['state']) && 
			($newOrderData['state'] == Mage_Sales_Model_Order::STATE_CANCELED))
		{
        	// TODO: Código de transação do pedido
        	//$orderTransaction = $newOrderData['some_attribute_value'];
			$orderTransaction = "a32ad4asdf435asdf435435as76"; 

			//Mage::log($observer->getEvent()->getPayment()->getMethodInstance()->getCode());	
			//Mage::log($observer->getEvent()->getTransaction());
			if(!is_null($orderTransaction) && !empty($orderTransaction))
			{
				//Mage::model();
				//$pagamentoOrderModel = Mage::getModel('pagamento/order');
				//$pagamentoOrderModel->cancellation($orderTransaction);
			}
		}          
	}
}