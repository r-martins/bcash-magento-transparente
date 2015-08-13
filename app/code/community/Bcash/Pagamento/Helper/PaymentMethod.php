<?php

class Bcash_Pagamento_Helper_PaymentMethod
{
	
	const CARD_TYPE = "CARD";
	const BANKSLIP_TYPE = "BANKSLIP";
	const ONLINE_TRANSFER_TYPE = "ONLINE_TRANSFER";
	
	private static $cards;
	private static $onlineTransfer;
	private static $bankSlip;
	
	public function __construct() {
		self::$cards = array(
			$this->createPayment(1,  'Visa', self::CARD_TYPE, 1.0, 12),
			$this->createPayment(2,  'Master', self::CARD_TYPE, 1.0, 12),
			$this->createPayment(37, 'American Express', self::CARD_TYPE, 1.0, 12),
			$this->createPayment(45, 'Aura', self::CARD_TYPE, 1.0, 24),
			$this->createPayment(55, 'Diners', self::CARD_TYPE, 1.0, 12),
			$this->createPayment(56, 'HiperCard', self::CARD_TYPE, 1.0, 12),
			$this->createPayment(63, 'Elo', self::CARD_TYPE, 1.0, 12)
		);
		
		self::$bankSlip = array(
			$this->createPayment(10, 'Boleto Bancário', self::BANKSLIP_TYPE, 0.01, 1)
		);
		
		self::$onlineTransfer = array(
			$this->createPayment(58, 'BB', self::ONLINE_TRANSFER_TYPE, 0.01, 1),
			$this->createPayment(59, 'Bradesco', self::ONLINE_TRANSFER_TYPE, 0.01, 1),
			$this->createPayment(60, 'Itaú', self::ONLINE_TRANSFER_TYPE, 0.01, 1),
			$this->createPayment(61, 'Banrisul', self::ONLINE_TRANSFER_TYPE, 0.01, 1),
			$this->createPayment(62, 'HSBC', self::ONLINE_TRANSFER_TYPE, 0.01, 1)
		);
	}
	
	private function createPayment($id, $title, $type, $minimunValue, $maxInstallments) {
		$payment = new stdClass();

		$payment->id = $id;
		$payment->title = $title;
		$payment->type = $type;
		$payment->minimunValue = $minimunValue;
		$payment->maxInstallments = $maxInstallments; 
		
		return $payment;
	}

	public function getPaymentMethods() {
		return array(
			self::CARD_TYPE => self::$cards,
			self::BANKSLIP_TYPE => self::$bankSlip,
			self::ONLINE_TRANSFER_TYPE => self::$onlineTransfer
		);
	}
}
