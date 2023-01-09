<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

/**
 * @package storefront
 *
 * @internal - may be changed in the future
 */
abstract class AbstractScssCompiler
{
    abstract public function compileString(
        AbstractCompilerConfiguration $config,
        string $scss,
        ?string $path = null
    ): string;
}
