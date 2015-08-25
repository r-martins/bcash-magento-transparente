<?php

require_once(Mage::getBaseDir("lib") . "/BcashApi/autoloader.php");

use Bcash\Service\Notification;
use Bcash\Domain\NotificationContent;
use Bcash\Domain\NotificationStatusEnum;
use Bcash\Exception\ValidationException;
use Bcash\Exception\ConnectionException;
use Bcash\Test\NotificationSimulator;

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

	public function indexAction()
	{
		// ...
		// Notification Simulator
		$this->notificationSimulator("http://magento1921.local/pagamento/notification/request", "1234", "ORDER-1", "1");
	}

	public function requestAction()
    {
		// POST request
		$transactionId = Mage::app()->getRequest()->getParam('transacao_id');
		$orderId = trim(stripslashes(Mage::app()->getRequest()->getParam('pedido')));
		$statusId = (int) Mage::app()->getRequest()->getParam('status_id');
		$status = Mage::app()->getRequest()->getParam('status');

		$notificationContent = new NotificationContent($transactionId, $orderId, $statusId);
        Mage::log($notificationContent);

		$notification = new Notification($this->email, $this->token, $notificationContent);
        $notification->enableSandBox($this->sandbox);

        $result = false;
        try {
            /**
            * Verificação de requisição válida
            */
            //valor dos produtos + frete + acrecimo - desconto
            // TODO: Buscar valor total do pedido registrado na transação
            $transactionValue = 273.20;
            $result = $notification->verify($transactionValue);

        } catch (ValidationException $e) {
            Mage::log("Validation error: " . $e->getMessage());
            Mage::log($e->getErrors());

        } catch (ConnectionException $e) {
            Mage::log("Connection error: " . $e->getMessage());
            Mage::log($e->getErrors());
        }

        if ($result == true) {            
            // Processamento da notificação no pedido
            $this->processNotification($transactionId, $orderId, $statusId);
        }
	}

	private function processNotification($transactionId, $orderId, $statusId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

        switch ($statusId) {
            case NotificationStatusEnum::APPROVED:
                $order->getPayment()->registerCaptureNotification($order->getBaseGrandTotal());
                $order->getPayment()->setTransactionId($transactionId);
                $order->save();
                break;
            case NotificationStatusEnum::IN_PROGRESS:
                //$order->getPayment()->registerCaptureNotification();
                //$order->getPayment()->setTransactionId($transactionId);
                //$order->save();
                break;
            case NotificationStatusEnum::CANCELLED:
                $order->registerCancellation('Pagamento cancelado', TRUE)->save();
                break;
            default:
                $order->registerCancellation('Falha', TRUE)->save();
                break;
        }
	}   

    private function notificationSimulator($notificationUrl, $transactionId, $orderId, $statusId) {
		//$notificationUrl = "https://hostofstore.com/address/alert";
		//$transactionId = 987654321;  // id transacao do bcash
		//$orderId = "my-store-1234"; // id pedido da sua loja
		//$statusId = 3; // Aprovada

		try {
		    $result = NotificationSimulator::test ($notificationUrl, $transactionId, $orderId, $statusId);

		    echo "<pre>";
		    var_dump($result);die;
		    echo "</pre>";

		} catch (ConnectionException $e) {
		    echo "ErroTeste: " . $e->getMessage() . "\n";
		    echo "<pre>";
		    var_dump($e->getErrors());die;
		    echo "</pre>";
		}
    }		
}