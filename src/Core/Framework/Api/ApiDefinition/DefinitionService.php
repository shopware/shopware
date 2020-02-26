<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

class DefinitionService
{
    public const API = 'api';
    public const SALES_CHANNEL_API = 'sales-channel-api';
    public const STORE_API = 'store-api';

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

    public function generate(string $format = 'openapi-3', string $type = self::API, ?int $version = null): array
    {
        if ($version === null) {
            $version = PlatformRequest::API_VERSION;
        }

        return $this->getGenerator($format, $version, $type)->generate($this->getDefinitions($type), $version, $type);
    }

    public function getSchema(string $format = 'openapi-3', string $type = self::API, ?int $version = null): array
    {
        if ($version === null) {
            $version = PlatformRequest::API_VERSION;
        }

        return $this->getGenerator($format, $version, $type)->getSchema($this->getDefinitions($type), $version);
    }

    /**
     * @throws ApiDefinitionGeneratorNotFoundException
     */
    private function getGenerator(string $format, int $version, string $type): ApiDefinitionGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($format, $version, $type)) {
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

        if ($type === self::SALES_CHANNEL_API || $type === self::STORE_API) {
            return $this->salesChannelDefinitionRegistry->getDefinitions();
        }

        throw new ApiDefinitionGeneratorNotFoundException($type);
    }
}
