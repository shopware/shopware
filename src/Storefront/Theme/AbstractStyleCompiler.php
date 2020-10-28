<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

abstract class AbstractStyleCompiler
{
    abstract public function getDecorated(): AbstractStyleCompiler;

    abstract public function compileStyles(StyleCompileContext $compileContext): string;
}
