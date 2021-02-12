<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * @Annotation
 */
class HttpCache extends ConfigurationAnnotation
{
    public const ALIAS = 'httpCache';

    /**
     * @var int|null
     */
    private $maxAge;

    /**
     * @var array|null
     */
    private $states;

    public function getAliasName()
    {
        return self::ALIAS;
    }

    public function allowArray()
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

    public function getStates(): array
    {
        return $this->states ?? [];
    }

    public function setStates(?array $states): void
    {
        $this->states = $states;
    }
}
