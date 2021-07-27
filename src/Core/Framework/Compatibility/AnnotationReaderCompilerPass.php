<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Compatibility;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @deprecated tag:v6.5.0 - Remove compatibility bridge to make parameters case insensitive
 * @see https://github.com/doctrine/annotations/issues/421
 */
class AnnotationReaderCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('annotations.reader')->setClass(AnnotationReader::class);
    }
}
