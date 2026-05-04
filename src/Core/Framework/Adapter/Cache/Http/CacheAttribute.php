<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * Value object extended for cache attribute in request
 *
 * @phpstan-type CacheAttributeArray array{ clientMaxAge?: int, sharedMaxAge?: int, maxAge?: int, states?: list<string> }
 * @phpstan-type CacheAttributeType CacheAttributeArray|bool|string|int|CacheAttribute
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
    public static function fromAttributeValue(array|bool|string|int|CacheAttribute|null $attributeValue): ?self
    {
        if ($attributeValue instanceof CacheAttribute) {
            return $attributeValue;
        }

        if (\is_array($attributeValue)) {
            return self::fromArray($attributeValue);
        }

        // from XML route definitions string values can come
        $attributeValue = filter_var($attributeValue, \FILTER_VALIDATE_BOOLEAN);
        if ($attributeValue === true) {
            return new self();
        }

        return null;
    }
}
