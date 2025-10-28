<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Analytics;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Package('data-services')]
class TokenService
{
    private const CONFIG_KEY_ANALYTICS_SECRET = 'core.analytics.secret';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly HttpClientInterface $client
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
    }

    private function generateAndPersistSecret(): string
    {
        $secret = bin2hex(random_bytes(16));

        $this->systemConfigService->set(self::CONFIG_KEY_ANALYTICS_SECRET, $secret);

        return $secret;
    }

    private function fetchToken(string $secret, string $referer): ResponseInterface
    {
        $url = 'http://localhost:8080/token';

        return $this->client->request('POST', $url, [
            'headers' => [
                'X-Client-Secret' => $secret,
                'Referer' => $referer,
            ],
        ]);
    }

    private function parseToken(ResponseInterface $response): ?Token
    {
        $data = $response->toArray();
        if (!isset($data['token']) || !isset($data['expires_at'])) {
            return null;
        }

        if (!\is_string($data['token']) || !\is_int($data['expires_at'])) {
            return null;
        }

        $expiresAt = new \DateTimeImmutable('@' . $data['expires_at']);

        return new Token($data['token'], $expiresAt);
    }
}
