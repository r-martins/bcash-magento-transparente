<?php
$installer = $this;
$installer->startSetup();
$installer->run("ALTER TABLE `{$installer->getTable('sales/quote')}` ADD `transactionIdBcash` varchar(255) DEFAULT NULL COMMENT 'Id da Transação com Bcash';");
$installer->run("ALTER TABLE `{$installer->getTable('sales/quote')}` ADD `statusBcash` varchar(255) DEFAULT NULL COMMENT 'Status da Transação com Bcash';");
$installer->run("ALTER TABLE `{$installer->getTable('sales/quote')}` ADD `descriptionStatusBcash` varchar(255) DEFAULT NULL COMMENT 'Descrição do Status da Transação com Bcash';");
$installer->run("ALTER TABLE `{$installer->getTable('sales/quote')}` ADD `paymentLinkBcash` varchar(255) DEFAULT NULL COMMENT 'Link de Pagamento da Transação com Bcash';");
$installer->endSetup();