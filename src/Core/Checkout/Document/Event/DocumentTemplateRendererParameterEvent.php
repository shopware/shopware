<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Framework\Struct\ExtendableTrait;
use Symfony\Contracts\EventDispatcher\Event;

class DocumentTemplateRendererParameterEvent extends Event
{
    use ExtendableTrait;

    /**
     * @var array
     */
    private $parameters;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
