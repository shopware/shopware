<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class ClientFactory
{
    public static function createClient($hosts): Client
    {
        $hosts = array_filter(explode(',', $hosts));

        $clientBuilder = ClientBuilder::create();
        $clientBuilder->setHosts($hosts);
        $clientBuilder->setSSLVerification(false);

        return $clientBuilder->build();
    }
}
