<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\ParametersTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ScriptIdQuery implements BuilderInterface
{
    use ParametersTrait;

    /**
     * @param array<mixed> $parameters
     */
    public function __construct(
        private readonly string $id,
        array $parameters = []
    ) {
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
