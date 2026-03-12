<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class StoreHandshake implements AppHandshakeInterface
{
    private const SBP_EXCEPTION_UNAUTHORIZED = 'ShopwarePlatformException-1';

    private const SBP_EXCEPTION_NO_LICENSE = 'ShopwarePlatformException-16';

    public function __construct(
        private readonly string $shopUrl,
        private readonly string $appEndpoint,
        private readonly string $appName,
        private readonly string $shopId,
        private readonly StoreClient $storeClient,
        private readonly string $shopwareVersion,
        #[\SensitiveParameter]
        private readonly ?string $currentAppSecret = null
    ) {
    }

    public function assembleRequest(): RequestInterface
    {
        $date = new \DateTime();
        $uri = new Uri($this->appEndpoint);

        $uri = Uri::withQueryValues($uri, [
            'shop-id' => $this->shopId,
            'shop-url' => $this->shopUrl,
            'timestamp' => (string) $date->getTimestamp(),
        ]);

        $signature = $this->signPayload($uri->getQuery());

        $headers = [
            'shopware-app-signature' => $signature,
            'sw-version' => $this->shopwareVersion,
        ];

        // Add shop signature for re-registration (secret rotation)
        if ($this->currentAppSecret !== null) {
            $shopSignature = hash_hmac('sha256', $uri->getQuery(), $this->currentAppSecret);
            $headers['shopware-shop-signature'] = $shopSignature;
        }

        return new Request(
            'GET',
            $uri,
            $headers
        );
    }

    public function fetchAppProof(): string
    {
        $proof = $this->shopId . $this->shopUrl . $this->appName;

        return $this->storeClient->signPayloadWithAppSecret($proof, $this->appName);
    }

    private function signPayload(string $payload): string
    {
        try {
            return $this->storeClient->signPayloadWithAppSecret($payload, $this->appName);
        } catch (\Exception $e) {
            if ($e instanceof ClientException) {
                $response = \json_decode($e->getResponse()->getBody()->getContents(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);

                if ($response['code'] === self::SBP_EXCEPTION_UNAUTHORIZED || $response['code'] === self::SBP_EXCEPTION_NO_LICENSE) {
                    throw AppException::licenseCouldNotBeVerified($this->appName, $e);
                }
            }

            throw AppException::registrationFailed(
                $this->appName,
                'Could not sign payload with store secret',
                $e
            );
        }
    }
}
