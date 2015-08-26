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
            // TODO: Verificar se pedido possui transação Bcash e registrar em histórico
        }
    }
}