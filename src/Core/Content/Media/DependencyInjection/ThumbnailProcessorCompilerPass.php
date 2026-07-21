<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DependencyInjection;

use Shopware\Core\Content\Media\Thumbnail\Processor\ImagickThumbnailProcessor;
use Shopware\Core\Content\Media\Thumbnail\Processor\ThumbnailProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('discovery')]
class ThumbnailProcessorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (
            $container->getParameter('shopware.media.thumbnail_processor') === 'imagick'
            && \extension_loaded('imagick')
        ) {
            $container->getDefinition(ThumbnailProcessorInterface::class)
                ->setClass(ImagickThumbnailProcessor::class);
        }
    }
}
