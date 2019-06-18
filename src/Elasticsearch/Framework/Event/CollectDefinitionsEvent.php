<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Event;

use Symfony\Contracts\EventDispatcher\Event;

class CollectDefinitionsEvent extends Event
{
    /**
     * @var bool[]
     */
    private $definitions = [];

    public function getDefinitions(): array
    {
        return array_keys($this->definitions);
    }

    public function add(string $class): self
    {
        $this->definitions[$class] = true;

        return $this;
    }
}
