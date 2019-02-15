<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

class ValueGeneratorConnectorRegistry
{
    /**
     * @var ValueGeneratorConnectorInterface[]
     */
    protected $connectors = [];

    /**
     * @var ValueGeneratorConnectorInterface[]
     */
    protected $mapped;

    public function __construct(iterable $connectors)
    {
        $this->connectors = $connectors;
    }

    public function getConnector($connectorId): ?ValueGeneratorConnectorInterface
    {
        if ($connectorId === null) {
            $connectorId = 'standard_value_generator_connector';
        }
        $this->mapConnectors();

        return $this->mapped[$connectorId] ?? null;
    }

    private function mapConnectors(): array
    {
        if ($this->mapped === null) {
            $this->mapped = [];

            /* @var ValueGeneratorInterface $serializer */
            foreach ($this->connectors as $connector) {
                $this->mapped[$connector->getConnectorId()] = $connector;
            }
        }

        return $this->mapped;
    }
}
