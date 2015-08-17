<?php

class Bcash_Pagamento_Model_Observer
{
	
	public function checkOrderState(Varien_Event_Observer $observer)
	{

		$origOrderData = $observer->getEvent()->getData('data_object')->getOrigData();
		$newOrderData = $observer->getEvent()->getData('data_object')->getData();

		if (($origOrderData['state'] !== $newOrderData['state']) && 
			($newOrderData['state'] == Mage_Sales_Model_Order::STATE_CANCELED))
		{
        	// TODO: Código de transação do pedido
        	//$orderTransaction = $newOrderData['some_attribute_value'];
			$orderTransaction = "blablabla"; 

			if(!is_null($orderTransaction) && !empty($orderTransaction))
			{
				self::cancelOrderTransaction($orderTransaction);     
			}        	          
		}              
	}

	private function cancelOrderTransaction($transactionId) 
	{
		Mage::log('Ok - ' . $transactionId);
	}
}