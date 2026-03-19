<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Symfony\UX\TwigComponent\ComponentMetadata;

/**
 * @internal
 */
#[Package('framework')]
interface ComponentMetadataProviderInterface
{
    public function metadataFor(string $name): ComponentMetadata;
}
