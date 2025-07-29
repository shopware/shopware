<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Lcobucci\JWT\Signer\Key\InMemory;
use phpseclib3\Crypt\RSA\Formats\Keys\JWK;
use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
final class PublicKeyLoader
{
    private const CACHE_KEY = 'admin_sso_public_key_storage';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoginConfigService $loginConfigService,
        private readonly CacheInterface $cache
    ) {
    }

    public function loadPublicKey(string $publicKeyId, bool $bypassCacheLoading = false): InMemory
    {
        $loginConfig = $this->loginConfigService->getConfig();
        if (!$loginConfig instanceof LoginConfig) {
            throw LoginException::configurationNotFound();
        }

        if ($bypassCacheLoading) {
            $publicKeyString = $this->requestPublicKeys($loginConfig);
            $publicKey = $this->preparePublicKey($publicKeyId, $publicKeyString);
            if (!$publicKey instanceof InMemory) {
                throw LoginException::publicKeyNotFound();
            }

            $this->updateCache($publicKeyString);

            return $publicKey;
        }

        $publicKey = $this->loadAndPreparePublicKey($loginConfig, $publicKeyId);
        if (!$publicKey instanceof InMemory) {
            throw LoginException::publicKeyNotFound();
        }

        return $publicKey;
    }

    private function requestPublicKeys(LoginConfig $loginConfig): string
    {
        $publicKeysResponse = $this->client->request('GET', $loginConfig->baseUrl . $loginConfig->jwksPath);

        return $publicKeysResponse->getContent();
    }

    private function loadAndPreparePublicKey(LoginConfig $loginConfig, string $publicKeyId): ?InMemory
    {
        $publicKeyString = $this->loadPublicKeyString($loginConfig);

        return $this->preparePublicKey($publicKeyId, $publicKeyString);
    }

    private function preparePublicKey(string $publicKeyId, string $publicKeyString): ?InMemory
    {
        try {
            $publicKeys = \json_decode($publicKeyString, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw LoginException::invalidPublicKey($publicKeyString);
        }

        $publicKey = null;
        foreach ($publicKeys['keys'] as $key) {
            if ($key['kid'] === $publicKeyId) {
                $publicKey = \phpseclib3\Crypt\PublicKeyLoader::load(
                    JWK::load(\json_encode($key, \JSON_THROW_ON_ERROR))
                );

                break;
            }
        }

        if ($publicKey === null) {
            return null;
        }

        $publicKeyToString = $publicKey->toString('pkcs8');
        if (!\is_string($publicKeyToString) || empty($publicKeyToString)) {
            return null;
        }

        return InMemory::plainText($publicKeyToString);
    }

    private function loadPublicKeyString(LoginConfig $loginConfig): string
    {
        return (string) $this->cache->get(self::CACHE_KEY, fn (): string => $this->requestPublicKeys($loginConfig));
    }

    private function updateCache(string $publicKeyString): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, fn (): string => $publicKeyString);
    }
}
