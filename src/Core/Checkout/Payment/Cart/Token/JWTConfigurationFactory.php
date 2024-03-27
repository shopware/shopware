<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Decoder;
use Lcobucci\JWT\Encoder;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use League\OAuth2\Server\CryptKey;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - Use \Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory instead
 */
#[Package('core')]
class JWTConfigurationFactory
{
    public static function createJWTConfiguration(
        Signer $signer,
        CryptKey $privateKey,
        CryptKey $publicKey,
        ?Encoder $encoder = null,
        ?Decoder $decoder = null
    ): Configuration {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        /** @var non-empty-string $privateKeyText */
        $privateKeyText = $privateKey->getKeyContents();
        /** @var non-empty-string $publicKeyText */
        $publicKeyText = $publicKey->getKeyContents();

        if ($privateKey->getKeyPath() === '') {
            $privateKey = InMemory::plainText($privateKeyText, $privateKey->getPassPhrase() ?? '');
            $publicKey = InMemory::plainText($publicKeyText, $publicKey->getPassPhrase() ?? '');
        } else {
            $privateKey = InMemory::file($privateKey->getKeyPath(), $privateKey->getPassPhrase() ?? '');
            $publicKeyPath = $publicKey->getKeyPath();
            \assert($publicKeyPath !== '');
            $publicKey = InMemory::file($publicKeyPath, $publicKey->getPassPhrase() ?? '');
        }

        $configuration = Configuration::forAsymmetricSigner(
            $signer,
            $privateKey,
            $publicKey,
            $encoder ?? new JoseEncoder(),
            $decoder ?? new JoseEncoder()
        );

        // add basic constraint for token signature validation
        $constraint = new SignedWith($signer, $publicKey);
        $configuration->setValidationConstraints($constraint);

        return $configuration;
    }
}
