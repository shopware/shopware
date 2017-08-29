#!/usr/bin/env php
<?php

const MAX_ROWS = 200000;//000;
//const MAX_ROWS = 100000;

use Symfony\Component\HttpFoundation\Request;

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__.'/../../../app/autoload.php';

$kernel = new AppKernel('prod', false);
$kernel->boot();


    /**
     * @return \Shopware\Framework\Api\WriteContext
     */
    function createWriteContext(): \Shopware\Framework\Api\WriteContext
    {
        $context = new \Shopware\Framework\Api\WriteContext();
        $context->set('s_core_shops.uuid', 'SWAG-CONFIG-SHOP-UUID-1');
        return $context;
    }


$container = $kernel->getContainer();
$connection = $container->get('dbal_connection');
$productResource = $container->get('shopware.product.product.resource');


echo "\nPreparing\n\n";
$writer = $container->get('shopware.framework.api.writer');
$products = require_once __DIR__ . '/_fixtures.php';

echo "\nInserting\n\n";
$start = time();

foreach ($products as $i => $product) {
    if(!($i%100)) {
        echo $i . "\t" . (time() - $start) . "Sek\n";
    }

    try {
        $writer->insert(
            $productResource,
            $product,
            createWriteContext()
        );

    } catch (\Exception $e) {
        print_r([
            $i,
            $e->getMessage(),
            $e->getTraceAsString(),
            $product
        ]);
        return;
    }

}
