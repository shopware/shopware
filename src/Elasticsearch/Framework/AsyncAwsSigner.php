<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Request;
use AsyncAws\Core\RequestContext;
use AsyncAws\Core\Signer\SignerV4;
use AsyncAws\Core\Stream\StringStream;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;

/**
 * @internal
 */
#[Package('framework')]
class AsyncAwsSigner
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly LoggerInterface $logger,
        private readonly string $service,
        private readonly string $region,
        private readonly CredentialProvider $credentialProvider,
    ) {
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        try {
            $transformed = $this->transformRequest($request);

            $credentials = $this->credentialProvider->getCredentials($this->configuration);
            if ($credentials === null) {
                throw ElasticsearchException::awsCredentialsNotFound();
            }

            $signer = new SignerV4($this->service, $this->region);
            $signer->sign($transformed, $credentials, new RequestContext());

            foreach ($transformed->getHeaders() as $key => $value) {
                $request = $request->withHeader($key, $value);
            }

            return $request;
        } catch (\Throwable $e) {
            $this->logger->error('Error signing request: ' . $e->getMessage());

            throw $e;
        }
    }

    private function transformRequest(RequestInterface $request): Request
    {
        $headers = [];
        foreach ($request->getHeaders() as $key => $value) {
            $headers[$key] = implode(', ', $value);
        }

        if (!isset($headers['Host']) && !isset($headers['host'])) {
            $headers['Host'] = $request->getUri()->getHost();
        }

        $hostKey = isset($headers['Host']) ? 'Host' : 'host';
        $parsedUrl = parse_url($headers[$hostKey]);

        if (isset($parsedUrl['host'])) {
            $headers[$hostKey] = $parsedUrl['host'];
        }

        parse_str($request->getUri()->getQuery(), $query);
        $query = array_filter($query, 'is_string');
        $query = $query === [] ? [] : array_combine(array_map('strval', array_keys($query)), $query);

        $body = (string) $request->getBody();
        $url = (string) $request->getUri();

        $transformed = new Request(
            $request->getMethod(),
            $url,
            $query,
            $headers,
            StringStream::create($body)
        );
        $transformed->setEndpoint($url);

        return $transformed;
    }
}
