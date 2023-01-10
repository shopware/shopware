<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Annotation;

use Shopware\Core\Framework\Routing\Annotation\BaseAnnotation;
use Shopware\Core\Framework\Script\Api\ResponseCacheConfiguration;

/**
 * @package storefront
 *
 * @Annotation
 */
class HttpCache extends BaseAnnotation
{
    public const ALIAS = 'httpCache';

    private ?int $maxAge = null;

    /**
     * @var list<string>|null
     */
    private ?array $states = null;

    public function getAliasName(): string
    {
        return self::ALIAS;
    }

    public function allowArray(): bool
    {
        return true;
    }

    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    public function setMaxAge(?int $maxAge): void
    {
        $this->maxAge = $maxAge;
    }

    /**
     * @return list<string>
     */
    public function getStates(): array
    {
        return $this->states ?? [];
    }

    /**
     * @param list<string>|null $states
     */
    public function setStates(?array $states): void
    {
        $this->states = $states;
    }

    /**
     * @internal only for use by the app system
     */
    public static function fromScriptResponseCacheConfig(ResponseCacheConfiguration $configuration): self
    {
        return new self([
            'states' => $configuration->getInvalidationStates(),
            'maxAge' => $configuration->getMaxAge(),
        ]);
    }
}
