<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PrivateHandshake implements AppHandshakeInterface
{
    public function __construct(
        private readonly string $shopUrl,
        #[\SensitiveParameter]
        private readonly string $secret,
        private readonly string $appEndpoint,
        private readonly string $appName,
        private readonly string $shopId,
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

        $signature = hash_hmac('sha256', $uri->getQuery(), $this->secret);

        $headers = [
            'shopware-app-signature' => $signature,
            'sw-version' => $this->shopwareVersion,
        ];

        // Add shop signature for re-registration
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
        return hash_hmac('sha256', $this->shopId . $this->shopUrl . $this->appName, $this->secret);
    }
}
