<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ChainProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Utils;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use OpenSearch\HttpClient\GuzzleHttpClientFactory;
use OpenSearch\TransportFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Profiler\ClientProfiler;

#[Package('framework')]
class ClientFactory
{
    /**
     * @param array{verify_server_cert: bool, cert_path?: string, cert_key_path?: string, sigV4?: array{enabled: bool, region?: string, service?: string, credentials_provider?: array{key_id?: string, secret_key?: string}}} $sslConfig
     */
    public static function createClient(string $hosts, LoggerInterface $logger, bool $debug, array $sslConfig): Client
    {
        $hostArray = array_values(array_filter(array_map('trim', explode(',', $hosts))));

        // Backward compatibility: use old ClientBuilder for multiple hosts support before v6.8.0.0
        if (\count($hostArray) > 1) {
            return self::createClientLegacy($hostArray, $logger, $debug, $sslConfig);
        }

        $host = self::normalizeHost($hostArray[0] ?? 'localhost:9200');
        $httpClient = self::createHttpClient($host, $logger, $debug, $sslConfig);

        $transport = (new TransportFactory())
            ->setHttpClient($httpClient)
            ->create();

        $client = new Client($transport);

        if ($debug) {
            $profiler = new ClientProfiler($transport);
            $profiler->setBaseUri(new Uri($host));

            return $profiler;
        }

        return $client;
    }

    /**
     * Legacy implementation using ClientBuilder for backward compatibility with multiple hosts.
     * This method will be removed in v6.8.0.0.
     *
     * @deprecated tag:v6.8.0 - Use createClient() instead
     *
     * @param list<string> $hosts
     * @param array{verify_server_cert: bool, cert_path?: string, cert_password?: string, cert_key_path?: string, cert_key_password?: string, sigV4?: array{enabled: bool, region?: string, service?: string, credentials_provider?: array{key_id?: string, secret_key?: string}}} $sslConfig
     */
    private static function createClientLegacy(array $hosts, LoggerInterface $logger, bool $debug, array $sslConfig): Client
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'Using multiple hosts in the Elasticsearch client configuration is deprecated and will be removed in v6.8.0.0.'
        );

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

            $credentialProvider = ChainProvider::createDefaultChain(null, $logger);

            $signer = new AsyncAwsSigner($configuration, $logger, $service, $region, $credentialProvider);
            $clientBuilder->setHandler($signer);
        }

        return $clientBuilder->build();
    }

    /**
     * @param non-empty-string $host
     * @param array{verify_server_cert: bool, cert_path?: string, cert_password?: string, cert_key_path?: string, cert_key_password?: string} $sslConfig
     */
    private static function createHttpClient(string $host, LoggerInterface $logger, bool $debug, array $sslConfig): GuzzleClient
    {
        $options = [
            'base_uri' => $host,
            'verify' => $sslConfig['verify_server_cert'],
        ];

        if (isset($sslConfig['cert_path'])) {
            $options['cert'] = [$sslConfig['cert_path'], $sslConfig['cert_password'] ?? ''];
        }

        if (isset($sslConfig['cert_key_path'])) {
            $options['ssl_key'] = [$sslConfig['cert_key_path'], $sslConfig['cert_key_password'] ?? ''];
        }

        $stack = new HandlerStack();
        $stack->setHandler(Utils::chooseHandler());

        if ($sslConfig['sigV4']['enabled'] ?? false) {
            $region = $sslConfig['sigV4']['region'] ?? '';
            $service = $sslConfig['sigV4']['service'] ?? 'es';
            $credentials = $sslConfig['sigV4']['credentials_provider'] ?? [];

            $configuration = Configuration::create([
                'region' => $region,
                'accessKeyId' => $credentials['key_id'] ?? null,
                'accessKeySecret' => $credentials['secret_key'] ?? null,
            ]);

            $credentialProvider = ChainProvider::createDefaultChain(null, $logger);

            $stack->push(Middleware::mapRequest(
                new AsyncAwsSigner($configuration, $logger, $service, $region, $credentialProvider)
            ));
        }
        $options['handler'] = $stack;

        return (new GuzzleHttpClientFactory(logger: $debug ? $logger : null))->create($options);
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeHost(string $host): string
    {
        if (!str_contains($host, '://')) {
            $host = 'http://' . $host;
        }

        $parts = parse_url($host);
        if ($parts === false || !isset($parts['host'])) {
            throw ElasticsearchException::invalidHostConfiguration(\sprintf('Invalid OpenSearch host "%s".', $host));
        }

        $scheme = $parts['scheme'] ?? 'http';
        $port = $parts['port'] ?? 9200;
        $path = $parts['path'] ?? '';
        $path = rtrim($path, '/');
        $userInfo = '';

        if (isset($parts['user'])) {
            $userInfo = $parts['user'];

            if (isset($parts['pass'])) {
                $userInfo .= ':' . $parts['pass'];
            }

            $userInfo .= '@';
        }

        return \sprintf('%s://%s%s:%d%s/', $scheme, $userInfo, $parts['host'], $port, $path);
    }
}
