<?php

class Bcash_Pagamento_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View {

    public function  __construct() {

        parent::__construct();

        $order = $this->getOrder();

        // TODO: Transação Bcash registrada no pedido
        //$helperTransaction = Mage::helper('pagamento/transaction')->getTransactionStatus('');

       //if ($this->_isAllowedAction('cancel') && $order->canCancel()) {
       
        	 $confirmationMessage = Mage::helper('core')->jsQuoteEscape(
                Mage::helper('sales')->__('Deseja realmente cancelar o pedido? A transação de pagamento também será cancelada.')
            );
            $this->_addButton('button_cancel_with_bcash', array(
                	            'label'     => 'Cancelar com Bcash',
                	            'onclick'   => 'deleteConfirm(\'' . $confirmationMessage . '\', \'' . $this->getUrl('pagamento/admin_sales_order/canceltransaction/') . '\')',
                	        ), 0, 100, 'header', 'header');
	        
        //}
    }
}