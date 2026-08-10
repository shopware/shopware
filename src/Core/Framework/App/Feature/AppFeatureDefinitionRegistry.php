<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppFeatureDefinitionRegistry
{
    /**
     * @var array<class-string<AppFeatureConfig>, AppFeatureDefinition<covariant AppFeatureConfig>>
     */
    private array $definitions = [];

    /**
     * @param iterable<AppFeatureDefinition<AppFeatureConfig>> $definitions
     */
    public function __construct(iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->getConfigClass()] = $definition;
        }
    }

    /**
     * @return list<AppFeatureDefinition<AppFeatureConfig>>
     */
    public function all(): array
    {
        /** @var list<AppFeatureDefinition<AppFeatureConfig>> */
        return array_values($this->definitions);
    }

    /**
     * @template T of AppFeatureConfig
     *
     * @param class-string<T> $featureClass
     *
     * @return AppFeatureDefinition<T>
     */
    public function forFeature(string $featureClass): AppFeatureDefinition
    {
        if (!isset($this->definitions[$featureClass])) {
            throw AppFeatureException::unknownFeature($featureClass);
        }

        /** @var AppFeatureDefinition<T> */
        return $this->definitions[$featureClass];
    }
}
