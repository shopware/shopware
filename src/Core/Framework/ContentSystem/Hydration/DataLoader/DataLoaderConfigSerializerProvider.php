<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class DataLoaderConfigSerializerProvider
{
    /**
     * @param ServiceLocator<AbstractContentDataLoaderConfigSerializer> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decode(string $source, array $data): AbstractContentDataLoaderConfig
    {
        if (!$this->locator->has($source)) {
            throw ContentSystemException::configSerializerNotRegistered($source);
        }

        return $this->locator->get($source)->decode($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(string $source, AbstractContentDataLoaderConfig $config): array
    {
        if (!$this->locator->has($source)) {
            throw ContentSystemException::configSerializerNotRegistered($source);
        }

        return $this->locator->get($source)->encode($config);
    }
}
