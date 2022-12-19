<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @package storefront
 *
 * @extends Collection<StorefrontPluginConfiguration>
 */
class StorefrontPluginConfigurationCollection extends Collection
{
    public function __construct(iterable $elements = [])
    {
        parent::__construct([]);

        foreach ($elements as $element) {
            $this->validateType($element);

            $this->set($element->getTechnicalName(), $element);
        }
    }

    public function add($element): void
    {
        $this->validateType($element);

        $this->set($element->getTechnicalName(), $element);
    }

    public function getByTechnicalName(string $name): ?StorefrontPluginConfiguration
    {
        return $this->filter(function (StorefrontPluginConfiguration $config) use ($name) {
            return $config->getTechnicalName() === $name;
        })->first();
    }

    public function getThemes(): StorefrontPluginConfigurationCollection
    {
        return $this->filter(function (StorefrontPluginConfiguration $configuration) {
            return $configuration->getIsTheme();
        });
    }

    public function getNoneThemes(): StorefrontPluginConfigurationCollection
    {
        return $this->filter(function (StorefrontPluginConfiguration $configuration) {
            return !$configuration->getIsTheme();
        });
    }

    protected function getExpectedClass(): ?string
    {
        return StorefrontPluginConfiguration::class;
    }
}
