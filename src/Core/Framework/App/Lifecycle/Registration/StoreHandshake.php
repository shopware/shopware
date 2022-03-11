<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\App\Exception\AppLicenseCouldNotBeVerifiedException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\Store\Services\StoreClient;

/**
 * @internal only for use by the app-system
 */
class StoreHandshake implements AppHandshakeInterface
{
    private const SBP_EXCEPTION_UNAUTHORIZED = 'ShopwarePlatformException-1';

    private const SBP_EXCEPTION_NO_LICENSE = 'ShopwarePlatformException-16';

    private string $shopUrl;

    private string $appEndpoint;

    private string $appName;

    private string $shopId;

    private StoreClient $storeClient;

    private string $shopwareVersion;

    public function __construct(string $shopUrl, string $appEndpoint, string $appName, string $shopId, StoreClient $storeClient, string $shopwareVersion)
    {
        $this->shopUrl = $shopUrl;
        $this->appEndpoint = $appEndpoint;
        $this->appName = $appName;
        $this->shopId = $shopId;
        $this->storeClient = $storeClient;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function assembleRequest(): RequestInterface
    {
        $date = new \DateTime();
        $uri = new Uri($this->appEndpoint);

        $uri = Uri::withQueryValues($uri, [
            'shop-id' => $this->shopId,
            'shop-url' => $this->shopUrl,
            'timestamp' => $date->getTimestamp(),
        ]);

        $signature = $this->signPayload($uri->getQuery());

        return new Request(
            'GET',
            $uri,
            [
                'shopware-app-signature' => $signature,
                'sw-version' => $this->shopwareVersion,
            ]
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
                $response = \json_decode($e->getResponse()->getBody()->getContents(), true, \JSON_THROW_ON_ERROR);

                if ($response['code'] === self::SBP_EXCEPTION_UNAUTHORIZED || $response['code'] === self::SBP_EXCEPTION_NO_LICENSE) {
                    throw new AppLicenseCouldNotBeVerifiedException(
                        'The license for the app "{{appName}}" could not be verified',
                        ['appName' => $this->appName],
                        $e
                    );
                }
            }

            throw new AppRegistrationException(
                'Could not sign payload with store secret for app: "{{appName}}"',
                ['appName' => $this->appName],
                $e
            );
        }
    }
}
