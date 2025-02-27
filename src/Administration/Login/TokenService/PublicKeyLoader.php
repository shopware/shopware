<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Lcobucci\JWT\Signer\Key\InMemory;
use phpseclib3\Crypt\RSA\Formats\Keys\JWK;
use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('after-sales')]
final class PublicKeyLoader
{
    private const CACHE_KEY = 'admin_sso_public_key_storage';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoginConfigService $loginConfigService,
        private readonly AdapterInterface $cache
    ) {
    }

    public function loadPublicKey(string $publicKeyId): InMemory
    {
        $publicKey = $this->loadPublicKeyByKeyId($publicKeyId);
        if ($publicKey === null) {
            $this->clearCache();
            $publicKey = $this->loadPublicKeyByKeyId($publicKeyId);
        }

        if ($publicKey === null) {
            throw LoginException::publicKeyNotFound();
        }

        return $publicKey;
    }

    private function loadPublicKeyByKeyId(string $publicKeyId): ?InMemory
    {
        $publicKeyString = $this->loadPublicKeys();
        $publicKeys = \json_decode($publicKeyString, true);

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

        $publicKeyString = $publicKey->toString('pkcs8');
        if (!\is_string($publicKeyString) || empty($publicKeyString)) {
            return null;
        }

        return InMemory::plainText($publicKeyString);
    }

    private function loadPublicKeys(): string
    {
        $cache = $this->cache->getItem(self::CACHE_KEY);

        if ($cache->isHit()) {
            return (string) $cache->get();
        }

        $loginConfig = $this->loginConfigService->getConfig();
        if (!$loginConfig instanceof LoginConfig) {
            throw LoginException::configurationNotFound();
        }

        $publicKeysResponse = $this->client->request('GET', $loginConfig->baseUrl . $loginConfig->jwksPath);

        $publicKeyString = $publicKeysResponse->getContent();

        $cache->set($publicKeyString);
        $this->cache->save($cache);

        return $publicKeyString;
    }

    private function clearCache(): void
    {
        $this->cache->clear(self::CACHE_KEY);
    }
}
