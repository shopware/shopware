<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

class ClientFactory
{
    public static function createClient(string $hosts, LoggerInterface $logger, bool $debug, array $sslConfig): Client
    {
        $hosts = array_filter(explode(',', $hosts));

        $clientBuilder = ClientBuilder::create();
        $clientBuilder->setHosts($hosts);

        if ($debug) {
            $clientBuilder->setTracer($logger);
        }

        $clientBuilder->setLogger($logger);

        if ($sslConfig['verify_server_cert'] === false) {
            $clientBuilder->setSSLVerification(false);
        }

        if (isset($sslConfig['cert_path'])) {
            $clientBuilder->setSSLCert($sslConfig['cert_path'], $sslConfig['cert_password'] ?? null);
        }

        if (isset($sslConfig['cert_key_path'])) {
            $clientBuilder->setSSLKey($sslConfig['cert_key_path'], $sslConfig['cert_key_password'] ?? null);
        }

        return $clientBuilder->build();
    }
}
