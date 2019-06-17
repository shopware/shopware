<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Event;

use Symfony\Component\EventDispatcher\Event;

class CollectDefinitionsEvent extends Event
{
    public const NAME = 'es.collect.definitions';

    /**
     * @var bool[]
     */
    private $definitions = [];

    public function getDefinitions(): array
    {
        return array_values($this->definitions);
    }

    public function add(string $class): self
    {
        $this->definitions[$class] = true;

        return $this;
    }
}
