<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ChainProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use OpenSearch\Client;
use OpenSearch\EndpointFactory;
use OpenSearch\HttpClient\GuzzleHttpClientFactory;
use OpenSearch\RequestFactory;
use OpenSearch\Serializers\SmartSerializer;
use OpenSearch\TransportFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;

#[Package('framework')]
class ClientFactory
{
    /**
     * @param array{verify_server_cert: bool, cert_path?: string, cert_key_path?: string, sigV4?: array{enabled: bool, region?: string, service?: string, credentials_provider?: array{key_id?: string, secret_key?: string}}} $sslConfig
     */
    public static function createClient(string $hosts, LoggerInterface $logger, bool $debug, array $sslConfig): Client
    {
        $hosts = array_values(array_filter(array_map('trim', explode(',', $hosts))));
        if ($hosts === []) {
            $hosts = ['localhost:9200'];
        }

        $hostUris = array_map(self::normalizeHost(...), $hosts);
        $httpClient = self::createHttpClient($hostUris, $logger, $debug, $sslConfig);

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

            $httpClient = new AsyncAwsSigner($configuration, $logger, $service, $region, $credentialProvider, $httpClient);
        }

        $httpClient = new RoundRobinHostHttpClient($httpClient, $hostUris);

        $httpFactory = new HttpFactory();
        $serializer = new SmartSerializer();
        $requestFactory = new RequestFactory($httpFactory, $httpFactory, $httpFactory, $serializer);
        $transport = (new TransportFactory())
            ->setHttpClient($httpClient)
            ->setRequestFactory($requestFactory)
            ->create();

        return new Client($transport, new EndpointFactory($serializer), []);
    }

    /**
     * @param non-empty-list<string> $hosts
     * @param array{verify_server_cert: bool, cert_path?: string, cert_password?: string, cert_key_path?: string, cert_key_password?: string} $sslConfig
     */
    private static function createHttpClient(array $hosts, LoggerInterface $logger, bool $debug, array $sslConfig): GuzzleClient
    {
        $options = [
            'base_uri' => $hosts[0],
            'verify' => $sslConfig['verify_server_cert'],
        ];

        if (isset($sslConfig['cert_path'])) {
            $options['cert'] = [$sslConfig['cert_path'], $sslConfig['cert_password'] ?? ''];
        }

        if (isset($sslConfig['cert_key_path'])) {
            $options['ssl_key'] = [$sslConfig['cert_key_path'], $sslConfig['cert_key_password'] ?? ''];
        }

        return (new GuzzleHttpClientFactory(logger: $debug ? $logger : null))->create($options);
    }

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
