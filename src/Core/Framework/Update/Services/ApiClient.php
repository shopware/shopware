<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Store\Services\OpenSSLVerifier;
use Shopware\Core\Framework\Update\Exception\UpdateApiSignatureValidationException;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\Framework\Update\VersionFactory;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class ApiClient
{
    private const SHOPWARE_SIGNATURE_HEADER = 'x-shopware-signature';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var OpenSSLVerifier
     */
    private $openSSLVerifier;

    /**
     * @var bool
     */
    private $shopwareUpdateEnabled;

    public function __construct(
        string $shopwareVersion,
        SystemConfigService $systemConfigService,
        OpenSSLVerifier $openSSLVerifier,
        Client $client,
        bool $shopwareUpdateEnabled
    ) {
        $this->shopwareVersion = $shopwareVersion;
        $this->systemConfigService = $systemConfigService;
        $this->openSSLVerifier = $openSSLVerifier;
        $this->shopwareUpdateEnabled = $shopwareUpdateEnabled;
        $this->client = $client;
    }

    public function checkForUpdates(bool $testMode = false): Version
    {
        if (!$this->shopwareUpdateEnabled) {
            return new Version();
        }

        if ($testMode === true) {
            return VersionFactory::createTestVersion();
        }
        $response = $this->client->get('/v1/release/update?' . http_build_query($this->getUpdateOptions()));

        $this->verifyResponseSignature($response);

        $data = json_decode((string) $response->getBody(), true);

        return VersionFactory::create($data);
    }

    private function getShopwareVersion(): string
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return '___VERSION___';
        }

        return $this->shopwareVersion;
    }

    private function getUpdateOptions(): array
    {
        return [
            'shopware_version' => $this->getShopwareVersion(),
            'channel' => $this->systemConfigService->get('core.update.channel'),
            'major' => 6,
            'code' => $this->systemConfigService->get('core.update.code'),
        ];
    }

    private function verifyResponseSignature(ResponseInterface $response): void
    {
        $signatureHeaderName = self::SHOPWARE_SIGNATURE_HEADER;
        $header = $response->getHeader($signatureHeaderName);
        if (!isset($header[0])) {
            throw new UpdateApiSignatureValidationException(sprintf('Signature not found in header "%s"', $signatureHeaderName));
        }

        $signature = $header[0];

        if (empty($signature)) {
            throw new UpdateApiSignatureValidationException(sprintf('Signature not found in header "%s"', $signatureHeaderName));
        }

        if (!$this->openSSLVerifier->isSystemSupported()) {
            return;
        }

        if ($this->openSSLVerifier->isValid($response->getBody()->getContents(), $signature)) {
            $response->getBody()->rewind();

            return;
        }

        throw new UpdateApiSignatureValidationException('Signature not valid');
    }
}
