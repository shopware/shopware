<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase\Services;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\JWT\Struct\JWKCollection;
use Shopware\Core\Framework\JWT\Struct\JWKStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 *
 * Fetches a JWKS from the SBP and stores it locally by the system to reduce the number of requests to the SBP.
 * The key gets refreshed only when forced to do so manually.
 *
 * @phpstan-import-type JSONWebKey from JWKStruct
 */
#[Package('checkout')]
final class KeyFetcher
{
    final public const CORE_STORE_JWKS = 'core.store.jwks';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly AbstractStoreRequestOptionsProvider $storeRequestOptionsProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getKey(Context $context, bool $refresh = false): JWKCollection
    {
        $key = $this->getStoredKey();

        if ($key && !$refresh) {
            return $key;
        }

        return $this->fetchAndStoreKey($context) ?? $key ?? throw AppException::jwksNotFound();
    }

    private function getStoredKey(): ?JWKCollection
    {
        $result = $this->systemConfigService->get(self::CORE_STORE_JWKS);

        if ($result && \is_string($result)) {
            /** @var array{keys: array<int, JSONWebKey>} $key */
            $key = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

            return JWKCollection::fromArray($key);
        }

        return null;
    }

    private function fetchAndStoreKey(Context $context): ?JWKCollection
    {
        try {
            $response = $this->client->request(
                'GET',
                '/inappfeatures/jwks',
                [
                    'query' => $this->storeRequestOptionsProvider->getDefaultQueryParameters($context),
                    'headers' => $this->storeRequestOptionsProvider->getAuthenticationHeader($context),
                ],
            );

            if ($response->getStatusCode() === 200) {
                $result = $response->getBody()->getContents();
                if (!$this->validateData($result)) {
                    $this->logger->error('Could not fetch the JWKS from the SBP', ['error' => 'Invalid data']);

                    return null;
                }
                $this->systemConfigService->set(self::CORE_STORE_JWKS, $result);

                /** @var array{keys: array<int, JSONWebKey>} $key */
                $key = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

                return JWKCollection::fromArray($key);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Could not fetch the JWKS from the SBP', ['error' => $e->getMessage()]);
        }

        $this->logger->error('Could not fetch the JWKS from the SBP');

        return null;
    }

    private function validateData(string $result): bool
    {
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

        if (!isset($data['keys']) || !\is_array($data['keys'])) {
            return false;
        }

        return true;
    }
}
