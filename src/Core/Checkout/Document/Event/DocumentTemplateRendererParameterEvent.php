<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ExtendableTrait;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('customer-order')]
class DocumentTemplateRendererParameterEvent extends Event
{
    use ExtendableTrait;

    public function __construct(private readonly array $parameters)
    {
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
