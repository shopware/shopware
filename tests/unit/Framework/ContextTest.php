<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Context
 */
class ContextTest extends TestCase
{
    public static function twigMethodProviders(): \Generator
    {
        yield 'enableInheritance' => ['{{ context.enableInheritance("print_r") }}'];
        yield 'disableInheritance' => ['{{ context.disableInheritance("print_r") }}'];
        yield 'scope' => ['{{ context.scope("system", "print_r") }}'];
    }

    public function testCallableCannotBeCalledFromTwig(): void
    {
        $context = Context::createDefaultContext();

        $twig = new Environment(new ArrayLoader([
            'tpl' => '{{ context.enableInheritance("print_r") }}',
        ]));

        static::expectException(\Throwable::class); // Twig versions prior to v3.8.x throw a different exception than the ones after, hence we can't check for a specific type.

        $twig->render('tpl', ['context' => $context]);
    }
}
