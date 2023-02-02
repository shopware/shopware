<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;
use Shopware\Core\Framework\Script\Api\ResponseCacheConfiguration;

/**
 * @Annotation
 */
class HttpCache extends ConfigurationAnnotation
{
    public const ALIAS = 'httpCache';

    private ?int $maxAge = null;

    private ?array $states = null;

    /**
     * @return string
     */
    public function getAliasName()
    {
        return self::ALIAS;
    }

    /**
     * @return bool
     */
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
