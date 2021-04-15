<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class StoreClientFactory
{
    private const SHOPWARE_SIGNATURE_HEADER = 'X-Shopware-Signature';
    private const CONFIG_KEY_STORE_API_URI = 'core.store.apiUri';

    private SystemConfigService $configService;

    private OpenSSLVerifier $openSSLVerifier;

    public function __construct(SystemConfigService $configService, OpenSSLVerifier $openSSLVerifier)
    {
        $this->configService = $configService;
        $this->openSSLVerifier = $openSSLVerifier;
    }

    public function create(): Client
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
            return $this->verifyResponseSignature($response);
        }));

        $config = $this->getClientBaseConfig();
        $config['handler'] = $stack;

        return new Client($config);
    }

    private function getClientBaseConfig(): array
    {
        return [
            'base_uri' => $this->configService->get(self::CONFIG_KEY_STORE_API_URI),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.api+json,application/json',
            ],
        ];
    }

    private function verifyResponseSignature(ResponseInterface $response): ResponseInterface
    {
        $signatureHeaderName = self::SHOPWARE_SIGNATURE_HEADER;
        $header = $response->getHeader($signatureHeaderName);
        if (!isset($header[0])) {
            throw new StoreSignatureValidationException(sprintf('Signature not found in header "%s"', $signatureHeaderName));
        }

        $signature = $header[0];

        if (empty($signature)) {
            throw new StoreSignatureValidationException(sprintf('Signature not found in header "%s"', $signatureHeaderName));
        }

        if (!$this->openSSLVerifier->isSystemSupported()) {
            return $response;
        }

        if ($this->openSSLVerifier->isValid($response->getBody()->getContents(), $signature)) {
            $response->getBody()->rewind();

            return $response;
        }

        throw new StoreSignatureValidationException('Signature not valid');
    }
}
