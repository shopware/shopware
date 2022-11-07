<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

/**
 * @internal - may be changed in the future
 */
abstract class AbstractScssCompiler
{
    abstract public function compileString(
        AbstractCompilerConfiguration $config,
        string $scss,
        ?string $path = null
    ): string;

    /**
     * If true, the caller should not process the result.
     * It will be processed internally and this SCSS Compiler takes care
     * about publishing the resulting css files
     */
    abstract public function filesHandledInternal(): bool;
}
