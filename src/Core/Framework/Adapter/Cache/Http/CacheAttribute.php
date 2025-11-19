<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * Value object extended for cache attribute in request
 *
 * @phpstan-type CacheAttributeArray array{ clientMaxAge?: int, sharedMaxAge?: int, maxAge?: int, states?: list<string> }
 * @phpstan-type CacheAttributeType CacheAttributeArray|true|CacheAttribute
 *
 * @internal
 */
#[Package('framework')]
readonly class CacheAttribute
{
    public function __construct(
        public ?int $maxAge = null,
        public ?int $sMaxAge = null,
        public ?string $policyModifier = null,
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         *
         * @var list<string>|null
         */
        public ?array $states = null,
    ) {
    }

    /**
     * @param CacheAttributeArray $attributeValue
     */
    public static function fromArray(array $attributeValue): self
    {
        return new self(
            maxAge: $attributeValue['clientMaxAge'] ?? null,
            sMaxAge: $attributeValue['sharedMaxAge'] ?? $attributeValue['maxAge'] ?? null,
            states: $attributeValue['states'] ?? null,
        );
    }

    /**
     * @param CacheAttributeType $attributeValue
     */
    public static function fromAttributeValue(array|bool|CacheAttribute|null $attributeValue): ?self
    {
        if ($attributeValue === null || $attributeValue === false) {
            return null;
        }

        if ($attributeValue === true) {
            return new self();
        }

        if ($attributeValue instanceof CacheAttribute) {
            return $attributeValue;
        }

        return self::fromArray($attributeValue);
    }
}
