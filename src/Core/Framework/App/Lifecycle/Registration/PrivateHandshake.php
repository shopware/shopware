<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

/**
 * @internal only for use by the app-system
 */
class PrivateHandshake implements AppHandshakeInterface
{
    private string $shopUrl;

    private string $secret;

    private string $appEndpoint;

    private string $appName;

    private string $shopId;

    private string $shopwareVersion;

    public function __construct(string $shopUrl, string $secret, string $appEndpoint, string $appName, string $shopId, string $shopwareVersion)
    {
        $this->shopUrl = $shopUrl;
        $this->secret = $secret;
        $this->appEndpoint = $appEndpoint;
        $this->appName = $appName;
        $this->shopId = $shopId;
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

        $signature = hash_hmac('sha256', $uri->getQuery(), $this->secret);

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
        return hash_hmac('sha256', $this->shopId . $this->shopUrl . $this->appName, $this->secret);
    }
}
