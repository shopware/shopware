<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping;

use Psr\Http\Message\RequestInterface;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;

class GoogleShoppingClientMock extends GoogleShoppingClient
{
    use GoogleAPIMockResponse;

    public function fetchAccessTokenWithAuthCode($code)
    {
        if ($code === 'VALID.AUTHORIZATION.CODE') {
            return [
                'access_token' => 'ya29.a0Adw1xeW4xei7do9ByIQaiPkxjw617yU1pAvYXRn',
                'refresh_token' => '1//0gTTgzGwplfyTCgYIARAAGBASNwF-L9Ir_K8q5k3l5M0ouz4hdlQ4hoE2vrqejreIjA',
                'created' => 1585199421,
                'id_token' => 'GOOGLE.' . base64_encode(json_encode(['name' => 'John Doe', 'email' => 'john.doe@example.com'])) . '.ID_TOKEN',
                'scope' => 'https://www.googleapis.com/auth/content https://www.googleapis.com/auth/adwords',
                'expires_in' => 3599,
            ];
        }

        return [];
    }

    public function isAccessTokenExpired()
    {
        return false;
    }

    public function asyncRequests(array $asyncRequests = [], ?bool $skipErrors = true, ?int $concurrency = 5): array
    {
        $result = [
            'responses' => [],
        ];

        foreach ($asyncRequests as $asyncRequest) {
            $expectedClass = $asyncRequest->getHeaderLine('X-Php-Expected-Class');

            if ($expectedClass && class_exists($expectedClass)) {
                $expectedResponse = $this->getExpectedResponse($expectedClass);
                $result['responses'][] = new $expectedClass($expectedResponse);
            }
        }

        return $result;
    }

    /**
     * @param string $expectedClass
     *
     * @return \Google_Collection
     */
    public function execute(RequestInterface $request, $expectedClass = null)
    {
        if ($expectedClass && class_exists($expectedClass)) {
            $expectedResponse = $this->getExpectedResponse($expectedClass);

            return new $expectedClass($expectedResponse);
        }

        return new \Google_Collection();
    }
}
