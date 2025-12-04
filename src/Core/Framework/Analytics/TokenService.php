<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Analytics;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Package('data-services')]
class TokenService
{
    private const CONFIG_KEY_ANALYTICS_SECRET = 'core.analytics.secret';

    private const RESPONSE_KEY_TOKEN = 'token';
    private const RESPONSE_KEY_EXPIRES_AT = 'expires_at';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly HttpClientInterface $client,
        private readonly string $gatewayBaseUrl
    ) {
    }

    public function generate(string $referer): ?Token
    {
        $secret = $this->systemConfigService->get(self::CONFIG_KEY_ANALYTICS_SECRET);

        if (!\is_string($secret) && $secret !== null) {
            return null;
        }

        if ($secret === null) {
            $secret = $this->generateAndPersistSecret();
        }

        try {
            $response = $this->fetchToken($secret, $referer);

            if ($response->getStatusCode() === Response::HTTP_FORBIDDEN) {
                // we need to rotate the secret
                $secret = $this->generateAndPersistSecret();
                $response = $this->fetchToken($secret, $referer);
            }

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                // TODO: log
                return null;
            }

            return $this->parseToken($response);
        } catch (TransportExceptionInterface) {
            // TODO: log
            // Gateway is unreachable
            return null;
        }
    }

    private function generateAndPersistSecret(): string
    {
        $secret = bin2hex(random_bytes(16));

        $this->systemConfigService->set(self::CONFIG_KEY_ANALYTICS_SECRET, $secret);

        return $secret;
    }

    private function fetchToken(string $secret, string $referer): ResponseInterface
    {
        return $this->client->request(Request::METHOD_POST, "{$this->gatewayBaseUrl}/token", [
            'headers' => [
                'X-Client-Secret' => $secret,
                'Referer' => $referer,
            ],
        ]);
    }

    private function parseToken(ResponseInterface $response): ?Token
    {
        $data = $response->toArray();
        if (!isset($data[self::RESPONSE_KEY_TOKEN]) || !isset($data[self::RESPONSE_KEY_EXPIRES_AT])) {
            return null;
        }

        if (!\is_string($data[self::RESPONSE_KEY_TOKEN]) || !\is_int($data[self::RESPONSE_KEY_EXPIRES_AT])) {
            return null;
        }

        $expiresAt = new \DateTimeImmutable('@' . $data[self::RESPONSE_KEY_EXPIRES_AT]);

        return new Token($data[self::RESPONSE_KEY_TOKEN], $expiresAt);
    }
}
