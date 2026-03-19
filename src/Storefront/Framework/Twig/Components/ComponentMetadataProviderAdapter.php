<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\ComponentMetadata;

/**
 * @internal
 */
#[Package('framework')]
class ComponentMetadataProviderAdapter implements ComponentMetadataProviderInterface
{
    public function __construct(private readonly ComponentFactory $componentFactory)
    {
    }

    public function metadataFor(string $name): ComponentMetadata
    {
        return $this->componentFactory->metadataFor($name);
    }
}
