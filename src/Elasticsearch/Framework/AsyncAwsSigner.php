<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Request;
use AsyncAws\Core\RequestContext;
use AsyncAws\Core\Signer\SignerV4;
use AsyncAws\Core\Stream\StringStream;
use GuzzleHttp\Ring\Future\CompletedFutureArray;
use OpenSearch\ClientBuilder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;

/**
 * @internal
 */
#[Package('core')]
class AsyncAwsSigner
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly LoggerInterface $logger,
        private readonly string $service,
        private readonly string $region,
    ) {
    }

    /**
     * @param array<string, mixed> $request
     */
    public function __invoke(array $request): CompletedFutureArray
    {
        try {
            $transformed = $this->transformRequest($request);

            $credentialProvider = ChainProvider::createDefaultChain(null, $this->logger);

            $credentials = $credentialProvider->getCredentials($this->configuration);
            if ($credentials === null) {
                throw ElasticsearchException::awsCredentialsNotFound();
            }

            $signer = new SignerV4($this->service, $this->region);

            $signer->sign($transformed, $credentials, new RequestContext());

            $request['headers'] = [];
            foreach ($transformed->getHeaders() as $key => $value) {
                $request['headers'][$key] = [$value];
            }

            return \call_user_func(ClientBuilder::defaultHandler(), $request);
        } catch (\Throwable $e) {
            $this->logger->error('Error signing request: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $request
     */
    private function transformRequest(array $request): Request
    {
        // fix for uppercase 'Host' array key in elasticsearch-php 5.3.1 and backward compatible
        // https://github.com/aws/aws-sdk-php/issues/1225
        $hostKey = isset($request['headers']['Host']) ? 'Host' : 'host';

        // Amazon ES/OS listens on standard ports (443 for HTTPS, 80 for HTTP).
        // Consequently, the port should be stripped from the host header.
        $parsedUrl = parse_url($request['headers'][$hostKey][0]);

        if (isset($parsedUrl['host'])) {
            $request['headers'][$hostKey][0] = $parsedUrl['host'];
        }

        parse_str($request['query_string'] ?? '', $query);
        $query = array_filter($query, 'is_string');
        $query = array_combine(array_map('strval', array_keys($query)), $query);

        $headers = [];
        foreach ($request['headers'] as $key => $value) {
            $headers[$key] = $value[0];
        }

        $url = $request['scheme'] . '://' . $request['headers'][$hostKey][0] . $request['uri'];

        $request = new Request(
            $request['http_method'],
            $url,
            $query,
            $headers,
            StringStream::create($request['body'] ?? '')
        );
        $request->setEndpoint($url);

        return $request;
    }
}
