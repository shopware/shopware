<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

class BuildValidationEvent extends Event implements ShopwareEvent, GenericEvent
{
    private DataValidationDefinition $definition;

    private Context $context;

    private DataBag $data;

    public function __construct(DataValidationDefinition $definition, DataBag $data, Context $context)
    {
        $this->definition = $definition;
        $this->context = $context;
        $this->data = $data;
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

    public function getData(): DataBag
    {
        return $this->data;
    }
}
