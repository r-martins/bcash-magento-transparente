<?php

class Bcash_Pagamento_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
	protected $_code = 'pagamento';
	protected $_canAuthorize = true;
	protected $_formBlockType = 'pagamento/form_payment';


	public function authorize($payment, Varien_Object $invoicePayment)
	{
		/* implementar a achamada a api bcash*/

		/* lancar Mage::throw */
		return $this;
	}

	public function validate(Varien_Object $info)
	{
		/* validar dados antes de processar */

		return $this;
	}

	public function assignData(mixed $data) {
		/* atribuir informacoes do pagamento para a info */
		return $this;
	}

}