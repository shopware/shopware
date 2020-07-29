<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

class ClientFactory
{
    public static function createClient($hosts  /*,LoggerInterface $logger*/): Client
    {
        $logger = func_get_arg(1);

        $hosts = array_filter(explode(',', $hosts));

        $clientBuilder = ClientBuilder::create();
        $clientBuilder->setHosts($hosts);
        $clientBuilder->setSSLVerification(false);

        if ($logger instanceof LoggerInterface) {
            $clientBuilder->setTracer($logger);
            $clientBuilder->setLogger($logger);
        }

        return $clientBuilder->build();
    }
}
