<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Annotation;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @deprecated tag:v6.6.0 - Will be removed use `defaults: {"_httpCache"=true}` or `{"_httpCache"={"maxAge": 360, "states": {"logged-in", "cart-filled"}}}` instead
 */
#[Package('storefront')]
class HttpCache
{
    final public const ALIAS = 'httpCache';

    private ?int $maxAge = null;

    /**
     * @var list<string>|null
     */
    private ?array $states = null;

    /**
     * @param array{maxAge?: int, states?: list<string>} $values
     */
    public function __construct(array $values)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        $this->maxAge = $values['maxAge'] ?? null;
        $this->states = $values['states'] ?? null;
    }

    public function getAliasName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return self::ALIAS;
    }

    public function allowArray(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return true;
    }

    public function getMaxAge(): ?int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return $this->maxAge;
    }

    public function setMaxAge(?int $maxAge): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        $this->maxAge = $maxAge;
    }

    /**
     * @return list<string>
     */
    public function getStates(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return $this->states ?? [];
    }

    /**
     * @param list<string>|null $states
     */
    public function setStates(?array $states): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        $this->states = $states;
    }
}
