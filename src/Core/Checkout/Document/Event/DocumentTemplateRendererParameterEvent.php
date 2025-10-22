<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ExtendableTrait;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
class DocumentTemplateRendererParameterEvent extends Event
{
    use ExtendableTrait;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(private readonly array $parameters)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
