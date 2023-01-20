<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @package core
 *
 * @internal
 */
class CachedEntitySchemaGenerator implements ApiDefinitionGeneratorInterface
{
    public const CACHE_KEY = 'core_framework_api_entity_schema';

    public function __construct(private EntitySchemaGenerator $innerService, private CacheInterface $cache)
    {
    }

    public function supports(string $format, string $api): bool
    {
        return $this->innerService->supports($format, $api);
    }

    /**
     * @return never
     */
    public function generate(array $definitions, string $api, string $apiType): array
    {
        $this->innerService->generate($definitions, $api, $apiType);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getSchema(array $definitions): array
    {
        return $this->cache->get(self::CACHE_KEY, function () use ($definitions) {
            return $this->innerService->getSchema($definitions);
        });
    }
}
