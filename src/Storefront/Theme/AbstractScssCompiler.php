<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal - may be changed in the future
 */
#[Package('storefront')]
abstract class AbstractScssCompiler
{
    abstract public function compileString(
        AbstractCompilerConfiguration $config,
        string $scss,
        ?string $path = null
    ): string;
}
