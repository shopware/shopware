<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(Context::class)]
class ContextTest extends TestCase
{
    public static function twigMethodProviders(): \Generator
    {
        yield 'enableInheritance' => ['{{ context.enableInheritance("print_r") }}'];
        yield 'disableInheritance' => ['{{ context.disableInheritance("print_r") }}'];
        yield 'scope' => ['{{ context.scope("system", "print_r") }}'];
        yield 'tpl' => ['{{ context.enableInheritance("print_r") }}'];
    }

    #[DataProvider(methodName: 'twigMethodProviders')]
    public function testCallableCannotBeCalledFromTwig(string $tpl): void
    {
        $context = Context::createDefaultContext();

        $twig = new Environment(new ArrayLoader([
            'tpl' => $tpl,
        ]));

        static::expectException(RuntimeError::class);

        $twig->render('tpl', ['context' => $context]);
    }
}
