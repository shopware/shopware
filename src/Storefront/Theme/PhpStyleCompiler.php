<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Formatter\Crunched;
use ScssPhp\ScssPhp\Formatter\Expanded;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;

class PhpStyleCompiler extends AbstractStyleCompiler
{
    /**
     * @var Compiler
     */
    private $scssCompiler;

    /**
     * @var bool
     */
    private $debug;

    public function __construct(bool $debug)
    {
        $this->scssCompiler = new Compiler();
        $this->scssCompiler->setImportPaths('');
        $this->scssCompiler->setFormatter($debug ? Expanded::class : Crunched::class);
        $this->debug = $debug;
    }

    public function getDecorated(): AbstractStyleCompiler
    {
        throw new DecorationPatternException(self::class);
    }

    public function compileStyles(StyleCompileContext $compileContext): string
    {
        $this->scssCompiler->addImportPath(function ($originalPath) use ($compileContext) {
            foreach ($compileContext->getResolveMappings() as $resolve => $resolvePath) {
                $resolve = '~' . $resolve;
                if (mb_strpos($originalPath, $resolve) === 0) {
                    $dirname = $resolvePath . dirname(mb_substr($originalPath, mb_strlen($resolve)));
                    $filename = basename($originalPath);
                    $extension = pathinfo($filename, PATHINFO_EXTENSION) === '' ? '.scss' : '';
                    $path = $dirname . DIRECTORY_SEPARATOR . $filename . $extension;
                    if (file_exists($path)) {
                        return $path;
                    }

                    $path = $dirname . DIRECTORY_SEPARATOR . '_' . $filename . $extension;
                    if (file_exists($path)) {
                        return $path;
                    }
                }
            }

            return null;
        });

        try {
            $cssOutput = $this->scssCompiler->compile($compileContext->getFullStyles());
        } catch (\Throwable $exception) {
            throw new ThemeCompileException(
                $compileContext->getThemeConfig()->getTechnicalName(),
                $exception->getMessage()
            );
        }
        $autoPreFixer = new Autoprefixer($cssOutput);

        return $autoPreFixer->compile($this->debug);
    }
}
