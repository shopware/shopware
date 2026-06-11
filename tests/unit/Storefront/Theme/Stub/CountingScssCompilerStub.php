<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Stub;

use Shopware\Storefront\Theme\AbstractCompilerConfiguration;
use Shopware\Storefront\Theme\AbstractScssCompiler;

/**
 * @internal
 */
class CountingScssCompilerStub extends AbstractScssCompiler
{
    public int $calls = 0;

    public function compileString(AbstractCompilerConfiguration $config, string $scss, ?string $path = null): string
    {
        ++$this->calls;

        return 'compiled-' . hash('xxh128', $scss);
    }
}
