<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Theme\PhpStyleCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StyleCompileContext;

class PhpStyleCompilerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var StyleCompileContext
     */
    private $styleCompileContext;

    public function setUp(): void
    {
        $mockSalesChannelId = '98432def39fc4624b33213a56b8c944d';

        $variables = <<<PHP_EOL
\$sw-color-brand-primary: #008490;
PHP_EOL;

        $styleMock = <<<PHP_EOL
.btn {
    &--primary {
        user-select: none;
        background-color: \$sw-color-brand-primary;
    }
}
PHP_EOL;

        $mockThemeConfiguration = new StorefrontPluginConfiguration();
        $mockThemeConfiguration->setTechnicalName('testTheme');

        $this->styleCompileContext = new StyleCompileContext($variables, $styleMock, $mockThemeConfiguration, [], $mockSalesChannelId);
    }

    public function testDebugFlag(): void
    {
        $nonDebugCompiler = new PhpStyleCompiler(false);
        $debugCompiler = new PhpStyleCompiler(true);

        $productionCss = $nonDebugCompiler->compileStyles($this->styleCompileContext);
        $debugCss = $debugCompiler->compileStyles($this->styleCompileContext);
        static::assertNotEquals($productionCss, $debugCss);
    }

    public function testAutoPrefixer(): void
    {
        static::markTestSkipped('Not yet implemented');
    }

    public function testPathResolving(): void
    {
        static::markTestSkipped('Not yet implemented');
    }
}
