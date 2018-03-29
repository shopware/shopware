<?php

namespace Shopware\Framework\Application;

use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\Struct;

class ApplicationInfo extends Struct
{
    /**
     * @var null|string
     */
    protected $applicationId;

    /**
     * @var null|string
     */
    protected $contextId;

    /**
     * @var null|StorefrontContext
     */
    protected $storefrontContext;

    public function getApplicationId(): ?string
    {
        return $this->applicationId;
    }

    /**
     * @param mixed $applicationId
     */
    public function setApplicationId($applicationId): void
    {
        $this->applicationId = $applicationId;
    }

    public function getContextId(): ?string
    {
        return $this->contextId;
    }

    /**
     * @param mixed $contextId
     */
    public function setContextId($contextId): void
    {
        $this->contextId = $contextId;
    }

    public function getStorefrontContext(): ?StorefrontContext
    {
        return $this->storefrontContext;
    }

    /**
     * @param mixed $storefrontContext
     */
    public function setStorefrontContext($storefrontContext): void
    {
        $this->storefrontContext = $storefrontContext;
    }
}