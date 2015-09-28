<?php

class Bcash_Pagamento_Model_System_Config_Source_Installments
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 1, 'label'  => Mage::helper('adminhtml')->__('1 parcela')),
            array('value' => 2, 'label'  => Mage::helper('adminhtml')->__('2 parcelas')),
            array('value' => 3, 'label'  => Mage::helper('adminhtml')->__('3 parcelas')),
            array('value' => 4, 'label'  => Mage::helper('adminhtml')->__('4 parcelas')),
            array('value' => 5, 'label'  => Mage::helper('adminhtml')->__('5 parcelas')),
            array('value' => 6, 'label'  => Mage::helper('adminhtml')->__('6 parcelas')),
            array('value' => 7, 'label'  => Mage::helper('adminhtml')->__('7 parcelas')),
            array('value' => 8, 'label'  => Mage::helper('adminhtml')->__('8 parcelas')),
            array('value' => 9, 'label'  => Mage::helper('adminhtml')->__('9 parcelas')),
            array('value' => 10, 'label' => Mage::helper('adminhtml')->__('10 parcelas')),
            array('value' => 11, 'label' => Mage::helper('adminhtml')->__('11 parcelas')),
            array('value' => 12, 'label' => Mage::helper('adminhtml')->__('12 parcelas')),
            array('value' => 13, 'label' => Mage::helper('adminhtml')->__('13 parcelas')),
            array('value' => 14, 'label' => Mage::helper('adminhtml')->__('14 parcelas')),
            array('value' => 15, 'label' => Mage::helper('adminhtml')->__('15 parcelas')),
            array('value' => 16, 'label' => Mage::helper('adminhtml')->__('16 parcelas')),
            array('value' => 17, 'label' => Mage::helper('adminhtml')->__('17 parcelas')),
            array('value' => 18, 'label' => Mage::helper('adminhtml')->__('18 parcelas')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
           1 => Mage::helper('adminhtml')->__('1 parcela'),
           2 => Mage::helper('adminhtml')->__('2 parcelas'),
           3 => Mage::helper('adminhtml')->__('3 parcelas'),
           4 => Mage::helper('adminhtml')->__('4 parcelas'),
           5 => Mage::helper('adminhtml')->__('5 parcelas'),
           6 => Mage::helper('adminhtml')->__('6 parcelas'),
           7 => Mage::helper('adminhtml')->__('7 parcelas'),
           8 => Mage::helper('adminhtml')->__('8 parcelas'),
           9 => Mage::helper('adminhtml')->__('9 parcelas'),
           10 => Mage::helper('adminhtml')->__('10 parcelas'),
           11 => Mage::helper('adminhtml')->__('11 parcelas'),
           12 => Mage::helper('adminhtml')->__('12 parcelas'),
           13 => Mage::helper('adminhtml')->__('13 parcelas'),
           14 => Mage::helper('adminhtml')->__('14 parcelas'),
           15 => Mage::helper('adminhtml')->__('15 parcelas'),
           16 => Mage::helper('adminhtml')->__('16 parcelas'),
           17 => Mage::helper('adminhtml')->__('17 parcelas'),
           18 => Mage::helper('adminhtml')->__('18 parcelas')
        );
    }
}