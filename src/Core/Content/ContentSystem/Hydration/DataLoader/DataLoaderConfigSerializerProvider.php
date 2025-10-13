<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('discovery')]
class DataLoaderConfigSerializerProvider
{
    /**
     * @param ServiceLocator<ContentDataLoaderConfigSerializerInterface> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decode(string $source, array $data): ContentDataLoaderConfigInterface
    {
        if (!$this->locator->has($source)) {
            throw ContentSystemException::configSerializerNotRegistered($source);
        }

        return $this->locator->get($source)->decode($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(string $source, ContentDataLoaderConfigInterface $config): array
    {
        if (!$this->locator->has($source)) {
            throw ContentSystemException::configSerializerNotRegistered($source);
        }

        return $this->locator->get($source)->encode($config);
    }
}
