<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Decoder;
use Lcobucci\JWT\Encoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use League\OAuth2\Server\CryptKey;

class JWTConfigurationFactory
{
    public static function createJWTConfiguration(
        Signer $signer,
        CryptKey $privateKey,
        CryptKey $publicKey,
        ?Encoder $encoder = null,
        ?Decoder $decoder = null
    ): Configuration {
        if ($privateKey->getKeyPath() === '') {
            $privateKey = InMemory::plainText($privateKey->getKeyContents(), $privateKey->getPassPhrase() ?? '');
            $publicKey = InMemory::plainText($publicKey->getKeyContents(), $publicKey->getPassPhrase() ?? '');
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
