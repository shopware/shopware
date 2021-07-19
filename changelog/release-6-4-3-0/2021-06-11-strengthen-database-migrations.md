---
title: Strengthen database migrations
issue: NEXT-15647

 
---
# Core
* Changed `Shopware\Core\Framework\Migration\MigrationRuntime` to provide a more helpful message on foreign key constraint exceptions.
* Changed `CREATE TABLE` query in `Shopware\Core\Migration\V6_4\Migration1594104496CashRounding` to `CREATE TABLE IF NOT EXIST`. 
* Changed `CREATE TABLE` query in `Shopware\Core\Migration\V6_4\Migration1610448012LandingPage` to `CREATE TABLE IF NOT EXIST`.
* Changed `CREATE TABLE` query in `Shopware\Core\Migration\V6_4\Migration1615366708AddProductStreamMapping` to `CREATE TABLE IF NOT EXIST`.
* Changed `CREATE TABLE` query in `Shopware\Core\Migration\V6_4\Migration1622104463AddPaymentTokenTable` to `CREATE TABLE IF NOT EXIST`.
