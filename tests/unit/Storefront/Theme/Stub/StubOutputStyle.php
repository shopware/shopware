<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Stub;

/**
 * A backed enum standing in for an output-style value, used to exercise the backed-enum
 * branch of CachedScssCompiler::resolveOutputStyle() regardless of the installed scssphp
 * version (where ScssPhp\ScssPhp\OutputStyle is a string-constant class on 1.x and a backed
 * enum on 2.x).
 *
 * @internal
 */
enum StubOutputStyle: string
{
    case Compressed = 'compressed';
}
