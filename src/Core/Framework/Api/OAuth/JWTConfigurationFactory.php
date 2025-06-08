<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256 as Hmac256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class JWTConfigurationFactory
{
    public static function createJWTConfiguration(): Configuration
    {
        /** @var non-empty-string $secret */
        $secret = (string) EnvironmentHelper::getVariable('APP_SECRET');
        $key = InMemory::plainText($secret);

        $configuration = Configuration::forSymmetricSigner(
            new Hmac256(),
            $key
        );

        $clock = new SystemClock(new \DateTimeZone(\date_default_timezone_get()));

        return $configuration->withValidationConstraints(
            new SignedWith(new Hmac256(), $key),
            new LooseValidAt($clock, null),
        );
    }
}
