<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

class ClientFactory
{
    public static function createClient(string $hosts, LoggerInterface $logger, bool $debug): Client
    {
        $hosts = array_filter(explode(',', $hosts));

        $clientBuilder = ClientBuilder::create();
        $clientBuilder->setHosts($hosts);

        if ($debug) {
            $clientBuilder->setTracer($logger);
        }

        $clientBuilder->setLogger($logger);

        return $clientBuilder->build();
    }
}
