<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class BuildValidationEvent extends Event implements ShopwareEvent, GenericEvent
{
    /**
     * @var DataValidationDefinition
     */
    private $definition;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array|null
     */
    private $payload;

    public function __construct(DataValidationDefinition $definition, Context $context, ?array $payload = null)
    {
        $this->definition = $definition;
        $this->context = $context;
        $this->payload = $payload;
    }

    public function getName(): string
    {
        return 'framework.validation.' . $this->definition->getName();
    }

    public function getDefinition(): DataValidationDefinition
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }
}
