<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Exception\DeprecatedSyntaxError;
use Shopware\Core\Framework\Log\Package;
use Twig\Source;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedSyntaxError::class)]
class DeprecatedSyntaxErrorTest extends TestCase
{
    public function testCreatesLocatedTwigSyntaxError(): void
    {
        $source = new Source('', 'example.html.twig');
        $exception = DeprecatedSyntaxError::invalid('Invalid declaration.', 3, $source);

        static::assertSame('Invalid declaration in "example.html.twig" at line 3.', $exception->getMessage());
        static::assertSame(3, $exception->getTemplateLine());
        static::assertSame($source, $exception->getSourceContext());
    }
}
