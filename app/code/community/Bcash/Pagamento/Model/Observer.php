<?php

/**
 * Class Bcash_Pagamento_Model_Observer
 */
class Bcash_Pagamento_Model_Observer
{
    /**
     * Método para verificar alteração de status do pedido
     *
     * @param Varien_Event_Observer $observer
     */
    public function checkOrderState(Varien_Event_Observer $observer)
    {
        $origOrderData = $observer->getEvent()->getData('data_object')->getOrigData();
        $newOrderData = $observer->getEvent()->getData('data_object')->getData();
        if (($origOrderData['state'] !== $newOrderData['state']) && ($newOrderData['state'] == Mage_Sales_Model_Order::STATE_CANCELED)) {
            // Verifica se pedido possui transação Bcash relacionada
            $order = $observer->getOrder()->getData();
            if(!empty($order['transaction_id_bcash']) && !is_null($order['transaction_id_bcash'])) {
                $orderId = $order['entity_id'];
                $order = Mage::getModel('sales/order')->load($orderId);
                $order->addStatusHistoryComment('Pedido não cancelado através do cancelamento Bcash. A transação ' . $order['transaction_id_bcash'] . ' não foi alterada.');
                $order->save();
            }
        }
    }

    /**
     * See etc/config.xml
     * Triggered by: Mage::dispatchEvent('checkout_type_onepage_save_order_after', array('order'=>$order, 'quote'=>$this->getQuote()));
     * @param Varien_Event_Observer $observer
     */
    public function saveOrderQuoteToSession($observer){
        /* @var $event Varien_Event */
        $event = $observer->getEvent();
        /* @var $order Mage_Sales_Model_Order */
        $order = $event->getOrder();
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = $event->getQuote();
        $session = Mage::getSingleton('checkout/session');
        $quoteId = $quote->getId();
        $orderId = $order->getId();
        $incrId  = $order->getIncrementId();
        Mage::log("Saving quote  [$quoteId] and order [$incrId] to checkout/session");
        $session->setData('OrderIdBcash',$orderId);
        $session->setData('OrderIncrementIdBcash',$incrId);
        $session->setData('QuoteIdBcash',$quoteId);
        unset($event);
        unset($order);
        unset($quote);
        unset($session);
        return $this;
    }
}