<?php

namespace Shopware\StorefrontApi\Firewall;

class ApplicationToken
{
    /**
     * @var string
     */
    private $applicationId;

    /**
     * @var string
     */
    private $contextId;

    public function __construct(string $applicationId, string $contextId)
    {
        $this->applicationId = $applicationId;
        $this->contextId = $contextId;
    }

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    public function getContextId(): string
    {
        return $this->contextId;
    }
}