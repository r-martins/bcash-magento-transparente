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
        $this->notificationSimulator("http://magento1921.local/pagamento/notification/request", "1234", "145000009", "3");
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
                // Registro de notificação recebida
                $order->addStatusHistoryComment('Notificação da transação de pagamento recebida. Status: ' . $status);
                $order->save();

                // Checa requisição válida através de valor total do pedido (valor dos produtos + frete + acréscimo - desconto)
                $transactionValue = $order->getBaseGrandTotal();
                $validNotification = $notification->verify($transactionValue);

                if ($validNotification == true) {
                    // Processamento da notificação no pedido
                    $this->processNotification($transactionId, $orderId, $statusId);
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
                $order->getPayment()->registerCaptureNotification($order->getBaseGrandTotal());
                $order->getPayment()->setTransactionId($transactionId);
                $order->save();
                break;
            case NotificationStatusEnum::IN_PROGRESS:
                $order->getPayment()->registerCaptureNotification();
                $order->getPayment()->setTransactionId($transactionId);
                $order->save();
                break;
            case NotificationStatusEnum::CANCELLED:
                $order->registerCancellation('Pagamento cancelado.', TRUE)->save();
                break;
            default:
                //$order->registerCancellation('Falha.', TRUE)->save();
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