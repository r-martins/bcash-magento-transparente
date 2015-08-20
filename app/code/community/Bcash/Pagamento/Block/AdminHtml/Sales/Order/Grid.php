<?php

class Bcash_Pagamento_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid
{   
    protected function _prepareMassaction()
    {
        parent::_prepareMassaction();
         
        // Append new mass action option 
        $this->getMassactionBlock()->addItem(
            'pagamento',
            array('label' => $this->__('Cancelar com Bcash'), 
                  'url'   => $this->getUrl('pagamento/admin_sales_order/masscanceltransaction/'))
        );
        // if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/cancel')) {
        //     $this->getMassactionBlock()->addItem('cancel_order', array(
        //          'label'=> Mage::helper('sales')->__('Cancel'),
        //          'url'  => $this->getUrl('*/sales_order/massCancel'),
        //     ));
        // }
    }
}