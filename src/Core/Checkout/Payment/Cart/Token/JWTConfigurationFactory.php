<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Decoder;
use Lcobucci\JWT\Encoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use League\OAuth2\Server\CryptKey;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class JWTConfigurationFactory
{
    public static function createJWTConfiguration(
        Signer $signer,
        CryptKey $privateKey,
        CryptKey $publicKey,
        ?Encoder $encoder = null,
        ?Decoder $decoder = null
    ): Configuration {
        /** @var non-empty-string $privateKeyText */
        $privateKeyText = $privateKey->getKeyContents();
        /** @var non-empty-string $publicKeyText */
        $publicKeyText = $publicKey->getKeyContents();

        if ($privateKey->getKeyPath() === '') {
            $privateKey = InMemory::plainText($privateKeyText, $privateKey->getPassPhrase() ?? '');
            $publicKey = InMemory::plainText($publicKeyText, $publicKey->getPassPhrase() ?? '');
        } else {
            $privateKey = InMemory::file($privateKey->getKeyPath(), $privateKey->getPassPhrase() ?? '');
            $publicKey = InMemory::file($publicKey->getKeyPath(), $publicKey->getPassPhrase() ?? '');
        }

        $configuration = Configuration::forAsymmetricSigner(
            $signer,
            $privateKey,
            $publicKey,
            $encoder,
            $decoder
        );

        // add basic constraint for token signature validation
        $constraint = new SignedWith($signer, $publicKey);
        $configuration->setValidationConstraints($constraint);

        return $configuration;
    }
}
