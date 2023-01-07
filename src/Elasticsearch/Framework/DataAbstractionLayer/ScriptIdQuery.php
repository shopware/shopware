<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\ParametersTrait;

/**
 * @package core
 */
class ScriptIdQuery implements BuilderInterface
{
    use ParametersTrait;

    private string $id;

    /**
     * @param array<mixed> $parameters
     */
    public function __construct(string $id, array $parameters = [])
    {
        $this->id = $id;
        $this->setParameters($parameters);
    }

    public function getType(): string
    {
        return 'script';
    }

    /**
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $query = ['id' => $this->id];
        $output = $this->processArray($query);

        return [$this->getType() => ['script' => $output]];
    }
}
