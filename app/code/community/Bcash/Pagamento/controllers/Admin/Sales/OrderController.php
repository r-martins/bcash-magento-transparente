<?php

/**
 * Controller Bcash_Pagamento_Admin_Sales_OrderController
 */
class Bcash_Pagamento_Admin_Sales_OrderController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Recebe requisição de cancelamento de um pedido
     */
    public function canceltransactionAction()
    {
        $orderId = trim(stripslashes($this->getRequest()->getParam('order_id')));

        if ($orderId) {
            // Cancelamento da transação do pedido
            if ($this->cancelBcashTransaction($orderId)) {
                Mage::getSingleton('adminhtml/session')->addSuccess('Pedido cancelado com sucesso.');
            } else {
                Mage::getSingleton('adminhtml/session')->addError('Não foi possível cancelar o pedido.');
            }

            // Retorna para tela do pedido
            $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
        }
    }

    /**
     * Recebe requisição de cancelamento de uma lista de pedidos
     */
    public function masscanceltransactionAction()
    {
        $ordersIds = $this->getRequest()->getParam('order_ids');

        if (!is_array($ordersIds)) {
            Mage::getSingleton('adminhtml/session')->addError('Selecione pedidos para cancelamento.');
        } else {
            try {
                // Counters
                $cancelSuccess = 0;
                $cancelFail = 0;

                foreach ($ordersIds as $orderId) {
                    // Cancelamento da transação do pedido
                    if ($this->cancelBcashTransaction($orderId)) {
                        $cancelSuccess++;
                    } else {
                        $cancelFail++;
                    }
                }

                if ($cancelSuccess > 0) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        Mage::helper('adminhtml')->__(
                            'Total de %d pedido(s) cancelados com sucesso.', $cancelSuccess
                        )
                    );
                }
                if ($cancelFail > 0) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('adminhtml')->__(
                            'Total de %d falha(s) no cancelamento.', $cancelFail
                        )
                    );
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        // Retorna para listagem de pedidos
        $this->_redirect('adminhtml/sales_order');
    }

    /**
     * Método para cancelamento da transação de pagamento do pedido
     *
     * @param $orderId
     * @return bool
     */
    private function cancelBcashTransaction($orderId)
    {
        // Carrega pedido a partir de Id de entidade
        $order = Mage::getModel('sales/order')->load($orderId);

        if ($order->getId()) {
            try {
                // TODO: Código de transação registrado no pedido
                //$orderTransaction = $newOrderData['some_attribute_value'];
                $orderTransaction = "a32ad4asdf435asdf435435as76";

                if (!is_null($orderTransaction) && !empty($orderTransaction)) {
                    $pagamentoOrderModel = Mage::getModel('pagamento/order');
                    $responseCancellation = $pagamentoOrderModel->cancellation($orderTransaction);

                    if ($responseCancellation != null) {
                        // TODO: Retorno correto da API
                        if ($responseCancellation['control_cancellation'] == true) {
                            // Registro do cancelamento no pedido
                            $order->registerCancellation('Cancelamento efetivado no Bcash. ', TRUE)->save();

                            return true;
                        } else {
                            // Registro em histórico do pedido de cancelamento não efetivado
                            $order->addStatusHistoryComment('Tentativa de cancelamento não efetivada no Bcash.' . $responseCancellation['message_fail']);
                            $order->save();

                            return false;
                        }
                    }
                }
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('The order has not been cancelled.'));
                Mage::log("Cancellation error: " . $e->getMessage());
            }
        }

        return false;
    }
}