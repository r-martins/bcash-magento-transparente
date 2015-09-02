<?php
/*
$installer = $this;
$installer->startSetup();
$installer->run("ALTER TABLE `{$installer->getTable('sales/quote')}` ADD `transactionIdBcash` varchar(255) DEFAULT NULL COMMENT 'Id da Transação com Bcash';");
$installer->run("ALTER TABLE `{$installer->getTable('sales/quote')}` ADD `statusBcash` varchar(255) DEFAULT NULL COMMENT 'Status da Transação com Bcash';");
$installer->run("ALTER TABLE `{$installer->getTable('sales/quote')}` ADD `descriptionStatusBcash` varchar(255) DEFAULT NULL COMMENT 'Descrição do Status da Transação com Bcash';");
$installer->run("ALTER TABLE `{$installer->getTable('sales/quote')}` ADD `paymentLinkBcash` varchar(255) DEFAULT NULL COMMENT 'Link de Pagamento da Transação com Bcash';");
$installer->endSetup();
*/

$installer = $this;
$installer->startSetup();

$installer->addAttribute(
    'quote',  /* order, quote, order_item, quote_item */
    'transactionIdBcash',
    array(
        'type' => 'varchar', /* int, varchar, text, decimal, datetime */
        'nullable' => true, /* default true */
        'grid' => true, /* or true if you wan't use this attribute on orders grid page */
    )
);

$installer->addAttribute(
    'quote',  /* order, quote, order_item, quote_item */
    'statusBcash',
    array(
        'type' => 'varchar', /* int, varchar, text, decimal, datetime */
        'nullable' => true, /* default true */
        'grid' => false, /* or true if you wan't use this attribute on orders grid page */
    )
);

$installer->addAttribute(
    'quote',  /* order, quote, order_item, quote_item */
    'descriptionStatusBcash',
    array(
        'type' => 'varchar', /* int, varchar, text, decimal, datetime */
        'nullable' => true, /* default true */
        'grid' => false, /* or true if you wan't use this attribute on orders grid page */
    )
);

$installer->addAttribute(
    'quote',  /* order, quote, order_item, quote_item */
    'paymentLinkBcash',
    array(
        'type' => 'varchar', /* int, varchar, text, decimal, datetime */
        'nullable' => true, /* default true */
        'grid' => true, /* or true if you wan't use this attribute on orders grid page */
    )
);

$installer->endSetup();