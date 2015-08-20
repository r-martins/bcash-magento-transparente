<?php

class Bcash_Pagamento_Admin_Sales_OrderController extends Mage_Adminhtml_Controller_Action
{

    public function canceltransactionAction()
    {        
        $orderId = $this->getRequest()->getParam('order_id');
          	
        if ($orderId) {
            $this->cancelBcashTransaction($orderId);
            
            $this->_redirect('adminhtml/sales_order/view', array('order_id' => $order->getId()));                    
        }
    }

    public function masscanceltransactionAction()
    {
        $ordersIds = $this->getRequest()->getParam('orders_id');

        if(!is_array($ordersIds)) {
            //Mage::getSingleton('adminhtml/session')->addError(Mage::helper('order')->__('Please select order(s).'));
        } 
        else {
            try {               
                foreach ($ordersIds as $orderId) {
                    $this->cancelBcashTransaction($orderId);
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('order')->__(
                        'Total de %d pedido(s) cancelados com Bcash.', count($ordersIds)
                        )
                    );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('adminhtml/sales_order');
    }	

    private function cancelBcashTransaction($orderId) {
        $order = Mage::getModel('sales/order')->load($orderId);

        if ($order->getId()) {
            try {
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

                // $order->cancel()
                //     ->save();
                // $this->_getSession()->addSuccess(
                //     $this->__('The order has been cancelled.')
                // );
            }
            catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
            catch (Exception $e) {
                $this->_getSession()->addError($this->__('The order has not been cancelled.'));
                Mage::logException($e);
            }                
        }
    }	
}