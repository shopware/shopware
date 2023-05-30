<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<StorefrontPluginConfiguration>
 */
#[Package('storefront')]
class StorefrontPluginConfigurationCollection extends Collection
{
    public function __construct(iterable $elements = [])
    {
        parent::__construct();

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
        return $this->filter(fn (StorefrontPluginConfiguration $config) => $config->getTechnicalName() === $name)->first();
    }

    public function getThemes(): StorefrontPluginConfigurationCollection
    {
        return $this->filter(fn (StorefrontPluginConfiguration $configuration) => $configuration->getIsTheme());
    }

    public function getNoneThemes(): StorefrontPluginConfigurationCollection
    {
        return $this->filter(fn (StorefrontPluginConfiguration $configuration) => !$configuration->getIsTheme());
    }

    protected function getExpectedClass(): ?string
    {
        return StorefrontPluginConfiguration::class;
    }
}
