<?php

require_once(Mage::getBaseDir("lib") . "/BcashApi/autoloader.php");

use Bcash\Service\Notification;
use Bcash\Domain\NotificationContent;
use Bcash\Domain\NotificationStatusEnum;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;
use Bcash\Test\NotificationSimulator;

/**
 * Controller Bcash_Pagamento_NotificationController
 */
class Bcash_Pagamento_NotificationController extends Mage_Core_Controller_Front_Action
{

    private $email;
    private $token;
    private $obj;
    private $sandbox;

    protected function _construct()
    {
        // access log (debug)
        Mage::log('Notification visitor: ' . Mage::helper('core/http')->getRemoteAddr());

        $this->obj = Mage::getSingleton('Bcash_Pagamento_Model_PaymentMethod');
        $this->email = $this->obj->getConfigData('email');
        $this->token = $this->obj->getConfigData('token');
        $this->sandbox = $this->obj->getConfigData('sandbox');
    }

    /**
     * Index
     */
    public function indexAction()
    {
        // Notification Simulator
        $this->notificationSimulator("http://magento1921.local/pagamento/notification/request", "503", "145000036", "8");
    }

    /**
     * Recebimento de notificações pela API
     */
    public function requestAction()
    {
        // GET THIS URL -> Mage::getUrl('pagamento/notification/request')

        // POST request
        $transactionId = Mage::app()->getRequest()->getParam('transacao_id');
        $orderId = trim(stripslashes(Mage::app()->getRequest()->getParam('pedido')));
        $statusId = (int)Mage::app()->getRequest()->getParam('status_id');
        $status = Mage::app()->getRequest()->getParam('status');

        // Instância de classe de notificação
        $notificationContent = new NotificationContent($transactionId, $orderId, $statusId);
        $notification = new Notification($this->email, $this->token, $notificationContent);
        $notification->enableSandBox($this->sandbox);

        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if ($order->getData() != null) {
                // Checa requisição válida através de valor total do pedido (valor dos produtos + frete + acréscimo - desconto)
                $transactionValue = $order->getBaseGrandTotal();
                $validNotification = $notification->verify($transactionValue);

                if ($validNotification == true) {
                    // Registro de notificação recebida
                    $order->addStatusHistoryComment('Notificação da transação de pagamento recebida. Status: ' . $status);
                    $order->save();

                    // Processamento da notificação no pedido
                    $this->processNotification($transactionId, $orderId, $statusId);
                }else {
                    Mage::log("Invalid bcash notification: Transaction: " . $transactionId . " - Status: " . $statusId);
                }
            } else {
                Mage::log("Pedido " . $orderId . " não identificado. ");
            }
        } catch (ValidationException $e) {
            Mage::log("Validation error: " . $e->getMessage());
            Mage::log($e->getErrors());

        } catch (ConnectionException $e) {
            Mage::log("Connection error: " . $e->getMessage());
            Mage::log($e->getErrors());
        }
    }

    /**
     * Atualização de status do pedido conforme retorno da notificação
     *
     * @param $transactionId
     * @param $orderId
     * @param $statusId
     * @throws Exception
     */
    private function processNotification($transactionId, $orderId, $statusId)
    {
        // Carrega pedido a partir de código incremental
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        switch ($statusId) {
            case NotificationStatusEnum::APPROVED:
            case NotificationStatusEnum::COMPLETED:
                    $BaseGrandtotal = $order->getBaseGrandTotal();
                    $payment = $order->getPayment();
                    $payment->setTransactionId($transactionId)
                            ->setCurrencyCode($order->getBaseCurrencyCode())
                            ->setPreparedMessage("Pagamento aprovado.")
                            ->setIsTransactionClosed(1)
                            ->registerCaptureNotification($BaseGrandtotal);
                    $order->save();

                    // Atualiza status na transação
                    $quoteId = $order->getQuoteId();
                    $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
                    $quote->setStatusBcash($statusId)
                          ->setDescriptionStatusBcash("Aprovada");
                    $quote->save();
                break;
            case NotificationStatusEnum::IN_PROGRESS:
                    $payment = $order->getPayment();
                    $payment->setTransactionId($transactionId);
                    $payment->setIsTransactionClosed(0);
                    $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array('Status'=>'Em andamento'));
                    $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
                break;
            case NotificationStatusEnum::CANCELLED:
                    $order->registerCancellation('Pagamento cancelado.', TRUE)->save();
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                    $order->save();

                    // Atualiza status na transação
                    $quoteId = $order->getQuoteId();
                    $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
                    $quote->setStatusBcash($statusId)
                          ->setDescriptionStatusBcash("Cancelada");
                    $quote->save();
                break;
            case NotificationStatusEnum::IN_DISPUTE:
                    $order->addStatusHistoryComment('A transação ' . $transactionId . ' está com status EM DISPUTA. Entre em contato com o Bcash.');
                    if($order->canHold()) {
                        $order->hold();
                    }else {
                        $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                    }
                    $order->save();
                break;
            case NotificationStatusEnum::CHARGEBACK:
                    $order->addStatusHistoryComment('A transação ' . $transactionId . ' está com status CHARGEBACK EM ANÁLISE. Entre em contato com o Bcash.');
                    if($order->canHold()) {
                        $order->hold();
                    }else {
                        $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                    }
                    $order->save();
                break;
            case NotificationStatusEnum::REFUNDED:
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
                break;
            default:
                $order->addStatusHistoryComment('Notificação da transação ' . $transactionId . ' sem ação identificada para o status ' . $statusId);
                $order->save();
                break;
        }
    }

    /**
     * Simulação de notificação enviada pelo Bcash na URL de retorno
     *
     * @param $notificationUrl
     * @param $transactionId
     * @param $orderId
     * @param $statusId
     */
    private function notificationSimulator($notificationUrl, $transactionId, $orderId, $statusId)
    {
        try {
            $result = NotificationSimulator::test($notificationUrl, $transactionId, $orderId, $statusId);

            echo "<pre>";
            var_dump($result);
            echo "</pre>";
        } catch (ConnectionException $e) {
        }
    }
}