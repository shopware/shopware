<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use AsyncAws\Core\Configuration;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ClientFactory
{
    /**
     * @param array{verify_server_cert: bool, cert_path?: string, cert_key_path?: string, sigV4?: array{enabled: bool, region?: string, service?: string, credentials_provider?: array{key_id?: string, secret_key?: string}}} $sslConfig
     */
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

        // Apply SigV4 signing if configured
        if ($sslConfig['sigV4']['enabled'] ?? false) {
            $region = $sslConfig['sigV4']['region'] ?? '';
            $service = $sslConfig['sigV4']['service'] ?? 'es';
            $credentials = $sslConfig['sigV4']['credentials_provider'] ?? [];

            $configuration = Configuration::create([
                'region' => $region,
                'accessKeyId' => $credentials['key_id'] ?? null,
                'accessKeySecret' => $credentials['secret_key'] ?? null,
            ]);

            $signer = new AsyncAwsSigner($configuration, $logger, $service, $region);
            $clientBuilder->setHandler($signer);
        }

        return $clientBuilder->build();
    }
}
