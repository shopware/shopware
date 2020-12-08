<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

use Shopware\Recovery\Common\HttpClient\Client;
use Shopware\Recovery\Common\HttpClient\ClientException;
use Shopware\Recovery\Install\Struct\Shop;

class WebserverCheck
{
    /**
     * @var string
     */
    private $pingUrl;

    /**
     * @var Client
     */
    private $httpClient;

    public function __construct(string $pingUrl, Client $httpClient)
    {
        $this->pingUrl = $pingUrl;
        $this->httpClient = $httpClient;
    }

    /**
     * @throws \RuntimeException
     */
    public function checkPing(Shop $shop): bool
    {
        $pingUrl = $this->buildPingUrl($shop);

        try {
            $response = $this->httpClient->get($pingUrl);
        } catch (ClientException $e) {
            throw new \RuntimeException('Could not check web server', $e->getCode(), $e);
        }

        if ($response->getCode() !== 200) {
            throw new \RuntimeException('Wrong http code ' . $response->getCode());
        }

        if ($response->getBody() !== 'pong') {
            throw new \RuntimeException('Content  ' . $response->getBody());
        }

        return true;
    }

    public function buildPingUrl(Shop $shop): string
    {
        if ($shop->basePath) {
            $shop->basePath = '/' . trim($shop->basePath, '/');
        }

        return 'http://' . $shop->host . $shop->basePath . '/' . $this->pingUrl;
    }
}
