<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Store\Exception\StoreLicenseDomainMissingException;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class StoreService
{
    public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    public const CONFIG_KEY_STORE_LICENSE_EDITION = 'core.store.licenseEdition';

    private const CONFIG_KEY_STORE_API_URI = 'core.store.apiUri';

    private const SHOPWARE_SIGNATURE_HEADER = 'X-Shopware-Signature';

    /**
     * @var SystemConfigService
     */
    private $configService;

    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var OpenSSLVerifier
     */
    private $openSSLVerifier;

    /**
     * @var string|null
     */
    private $instanceId;

    final public function __construct(SystemConfigService $configService, OpenSSLVerifier $openSSLVerifier, string $shopwareVersion, ?string $instanceId)
    {
        $this->configService = $configService;
        $this->openSSLVerifier = $openSSLVerifier;
        $this->shopwareVersion = $shopwareVersion;
        $this->instanceId = $instanceId;
    }

    /**
     * @throws StoreLicenseDomainMissingException
     */
    public function getDefaultQueryParameters(string $language, bool $checkLicenseDomain = true): array
    {
        $licenseDomain = $this->configService->get(self::CONFIG_KEY_STORE_LICENSE_DOMAIN);

        if ($checkLicenseDomain && !$licenseDomain) {
            throw new StoreLicenseDomainMissingException();
        }

        return [
            'shopwareVersion' => $this->getShopwareVersion(),
            'language' => $language,
            'domain' => $licenseDomain ?? '',
        ];
    }

    public function getShopwareVersion(): string
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return '___VERSION___';
        }

        return $this->shopwareVersion;
    }

    public function createClient(): Client
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
            return $this->verifyResponseSignature($response);
        }));

        $config = $this->getClientBaseConfig();
        $config['handler'] = $stack;

        return new Client($config);
    }

    public function fireTrackingEvent(string $eventName, array $additionalData = []): ?array
    {
        if (!$this->instanceId) {
            return null;
        }

        $additionalData['shopwareVersion'] = $this->getShopwareVersion();
        $payload = [
            'additionalData' => $additionalData,
            'instanceId' => $this->instanceId,
            'event' => $eventName,
        ];

        $client = new Client($this->getClientBaseConfig());

        try {
            $response = $client->post('/swplatform/tracking/events', ['json' => $payload]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
        }

        return null;
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
