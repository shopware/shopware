<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal - may be changed in the future
 */
#[Package('framework')]
class ScssPhpCompiler extends AbstractScssCompiler
{
    private readonly Compiler $compiler;

    private readonly Filesystem $filesystem;

    public function __construct(
        ?Compiler $compiler = null,
        ?Filesystem $filesystem = null,
    ) {
        $this->compiler = $compiler ?? new Compiler();
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function compileString(AbstractCompilerConfiguration $config, string $scss, ?string $path = null): string
    {
        // Reset the injected Compiler's settable state to its scssphp defaults so consecutive
        // compileString() calls don't inherit each other's output style or import paths.
        $this->compiler->setOutputStyle(OutputStyle::EXPANDED);
        $this->compiler->setImportPaths([]);

        $outputStyle = $config->getValue('outputStyle');
        if ($outputStyle === OutputStyle::COMPRESSED || $outputStyle === OutputStyle::EXPANDED) {
            $this->compiler->setOutputStyle($outputStyle);
        }

        $importPaths = $config->getValue('importPaths');
        if ($importPaths !== null) {
            $this->compiler->setImportPaths($importPaths);
        }

        if ($path !== null && $this->filesystem->exists($path)) {
            return $this->compiler->compileFile($path)->getCss();
        }

        return $this->compiler->compileString($scss)->getCss();
    }
}
