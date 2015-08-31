<?php
/* @var $installer Mage_Sales_Model_Mysql4_Setup */
$installer = $this;

$installer->startSetup();

/**
 * Adds an attribute of the code pagamento_info to the order object
 * As this is a flat table, it adds the column to the table for you
 **/
$installer->addAttribute('order', 'transactionId', array('type'=>'text'));
$installer->addAttribute('order', 'statusBcash', array('type'=>'text'));
$installer->addAttribute('order', 'descriptionStatusBcash', array('type'=>'text'));
$installer->addAttribute('order', 'paymentLinkBcash', array('type'=>'text'));

$attribute  = array(
    'type'            => 'varchar',
    'backend_type'    => 'varchar',
    'frontend_input'  => 'varchar',
    'is_user_defined' => true,
    'label'           => 'Id Bcash',
    'visible'         => true,
    'required'        => false,
    'user_defined'    => false,
    'searchable'      => true,
    'filterable'      => true,
    'comparable'      => false,
    'default'         => ''
);

$installer->addAttribute('order', 'transactionIdBcash', $attribute);
$installer->addAttribute('quote', 'transactionIdBcash', $attribute);

$attribute['label'] = 'Status Bcash';
$installer->addAttribute('order', 'statusBcash', $attribute);
$installer->addAttribute('quote', 'statusBcash', $attribute);

$attribute['label'] = 'Status Bcash Descrição';
$installer->addAttribute('order', 'descriptionStatusBcash', $attribute);
$installer->addAttribute('quote', 'descriptionStatusBcash', $attribute);

$attribute['label'] = 'URL de Pagamento';
$installer->addAttribute('order', 'paymentLinkBcash', $attribute);
$installer->addAttribute('quote', 'paymentLinkBcash', $attribute);

$installer->endSetup();