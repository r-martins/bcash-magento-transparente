<?php

class Bcash_Pagamento_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
	protected $_code = 'pagamento';
	protected $_isGateway = true;
	protected $_canCapture = true;
	protected $_canAuthorize = true;
	protected $_canOrder = true;
	protected $_formBlockType = 'pagamento/form_payment';

	public function authorize(Varien_Object $payment, $amount)
	{
		/* implementar a achamada a api bcash*/

		/* lancar Mage::throw */
		return $this;
	}

	public function validate()
	{
		/* validar dados antes de processar */

		return $this;
	}

	public function assignData($data) {
		/* atribuir informacoes do pagamento para a info */
		return $this;
	}

	public function order(Varien_Object $payment, $amount)
	{
		// initialize your gateway transaction as needed
		// $gateway is just an imaginary example of course
		Mage::throwException("Make Order Bcash");
        return $this;
    }

	public function gatewayResponseAction()
	{
		// again the imaginary example $gateway
		$order = Mage::getModel('sales/order')->load( $gateway->getOrderId() );
		$payment = $order->getPayment();
		$transaction = $payment->getTransaction( $gateway->getTransactionId() );

		if ($gateway->isSuccess())
		{
			$payment->registerCaptureNotification( $gateway->getAmount() );
			$payment->save();
			$order->save();
			$this->_redirect('checkout/onepage/success');
		}
		else
		{
			Mage::getSingleton('core/session')
				->addError($gateway->getErrorMessage() );
			// set quote active again, and cancel order
			// so the user can make a new order
			$quote = Mage::getModel('sales/quote')->load( $order->getQuoteId() );
			$quote->setIsActive( true )->save();
			$order->cancel()->save();
			$this->_redirect('checkout/onepage');
		}
	}


}