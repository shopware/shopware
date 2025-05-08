<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT\Constraints;

use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Services\DecodedPurchasesCollectionStruct;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('checkout')]
final readonly class MatchesLicenceDomain implements Constraint
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {
    }

    public function assert(Token $token): void
    {
        $domain = $this->systemConfigService->get(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN);

        if (!$domain) {
            throw JWTException::missingDomain();
        }

        if (!$token instanceof UnencryptedToken) {
            throw JWTException::invalidJwt('Incorrect token type');
        }

        $purchases = DecodedPurchasesCollectionStruct::fromArray($token->claims()->all());

        $firstPurchase = $purchases->first();
        if (!$firstPurchase) {
            throw JWTException::invalidJwt('No purchases found in JWT');
        }

        if ($firstPurchase->sub !== $domain) {
            throw JWTException::invalidDomain($firstPurchase->sub);
        }
    }
}
