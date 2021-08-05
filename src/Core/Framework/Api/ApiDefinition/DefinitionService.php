<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

class DefinitionService
{
    public const API = 'api';
    public const STORE_API = 'store-api';

    public const TypeJsonApi = 'jsonapi';
    public const TypeJson = 'json';

    /**
     * @var ApiDefinitionGeneratorInterface[]
     */
    private $generators;

    /**
     * @var SalesChannelDefinitionInstanceRegistry
     */
    private $salesChannelDefinitionRegistry;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry,
        ApiDefinitionGeneratorInterface ...$generators
    ) {
        $this->generators = $generators;
        $this->salesChannelDefinitionRegistry = $salesChannelDefinitionRegistry;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function generate(string $format = 'openapi-3', string $type = self::API, string $apiType = self::TypeJsonApi): array
    {
        return $this->getGenerator($format, $type)->generate($this->getDefinitions($type), $type, $apiType);
    }

    public function getSchema(string $format = 'openapi-3', string $type = self::API): array
    {
        return $this->getGenerator($format, $type)->getSchema($this->getDefinitions($type));
    }

    /**
     * @throws ApiDefinitionGeneratorNotFoundException
     */
    private function getGenerator(string $format, string $type): ApiDefinitionGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($format, $type)) {
                return $generator;
            }
        }

        throw new ApiDefinitionGeneratorNotFoundException($format);
    }

    /**
     * @throws ApiDefinitionGeneratorNotFoundException
     *
     * @return EntityDefinition[]|SalesChannelDefinitionInterface[]
     */
    private function getDefinitions(string $type): array
    {
        if ($type === self::API) {
            return $this->definitionRegistry->getDefinitions();
        }

        if ($type === self::STORE_API) {
            return $this->salesChannelDefinitionRegistry->getDefinitions();
        }

        throw new ApiDefinitionGeneratorNotFoundException($type);
    }
}
