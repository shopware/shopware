<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Twig\SecurityExtension
 */
class SecurityExtensionTest extends TestCase
{
    /**
     * @dataProvider notAllowedTemplates
     */
    public function testNotAllowedTemplates(string $template): void
    {
        // Depending on the twig version it might throw a RuntimeError or a TypeError,
        // all we care about is that it throws
        $this->expectException(\Throwable::class);
        $this->runTwig($template);
    }

    public static function notAllowedTemplates(): \Generator
    {
        yield 'map not allowed function' => ['{{ ["a", "b", "c"]|map("str_rot13")|join }}'];

        yield 'map not allowed callback function string' => ['{{ ["a", "b", "c"]|map("\\\\Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGadget::do")|join }}'];

        yield 'map not allowed callback function array' => ['{{ ["a", "b", "c"]|map([\'\\\\Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGadget\', \'do\'])|join }}'];

        yield 'map on array throws error' => ['{{ ["a", "b", "c"]|map([\'SecurityExtensionGadget\', \'do\'])|join }}'];

        yield 'reduce not allowed function' => ['{{ ["a", "b", "c"]|reduce("empty")|join }}'];

        yield 'reduce not allowed callback function string' => ['{{ ["a", "b", "c"]|reduce("\\\\Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGadget::do")|join }}'];

        yield 'reduce not allowed callback function array' => ['{{ ["a", "b", "c"]|reduce([\'\\\\Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGadget\', \'do\'])|join }}'];

        yield 'reduce on array throws error' => ['{{ ["a", "b", "c"]|reduce([\'SecurityExtensionGadget\', \'do\'])|join }}'];

        yield 'filter not allowed function' => ['{{ ["a", "b", "c"]|filter("str_rot13")|join }}'];

        yield 'filter not allowed callback function string' => ['{{ ["a", "b", "c"]|filter("\\\\Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGadget::do")|join }}'];

        yield 'filter not allowed callback function array' => ['{{ ["a", "b", "c"]|filter([\'\\\\Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGadget\', \'do\'])|join }}'];

        yield 'filter on array throws error' => ['{{ ["a", "b", "c"]|filter([\'SecurityExtensionGadget\', \'do\'])|join }}'];

        yield 'sort not allowed function' => ['{{ ["a", "b", "c"]|sort("str_rot13")|join }}'];

        yield 'sort not allowed callback function string' => ['{{ ["a", "b", "c"]|sort("\\\\Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGadget::do")|join }}'];

        yield 'sort not allowed callback function array' => ['{{ ["a", "b", "c"]|sort([\'\\\\Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGadget\', \'do\'])|join }}'];

        yield 'sort on array throws error' => ['{{ ["a", "b", "c"]|sort([\'SecurityExtensionGadget\', \'do\'])|join }}'];
    }

    public function testMapWithAllowedFunction(): void
    {
        static::assertSame('nop', $this->runTwig('{{ ["a", "b", "c"]|map("str_rot13")|join }}', ['str_rot13']));
    }

    public function testMapWithAllowedClosure(): void
    {
        static::assertSame(
            'TEST',
            $this->runTwig(
                '{{ ["test"]|map(\'Shopware\\\\Tests\\\\Unit\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\SecurityExtensionGoodClass::upper\')|join }}',
                ['Shopware\\Tests\\Unit\\Core\\Framework\\Adapter\\Twig\\SecurityExtensionGoodClass::upper'],
            )
        );
    }

    public function testMapWithClosure(): void
    {
        static::assertSame('a-testb-testc-test', $this->runTwig('{{ ["a", "b", "c"]|map(v => (v ~ "-test"))|join }}'));
    }

    public function testReduceAllowedFunction(): void
    {
        static::assertSame('6', $this->runTwig('{{ [1 , 5]|reduce((a, b) => a + b)|json_encode|raw }}'));
    }

    public function testReduceOnIterator(): void
    {
        static::assertSame('3', $this->runTwig('{{ test|reduce((a, b) => a + b)|json_encode|raw }}', [], ['test' => new \ArrayIterator([1, 2])]));
    }

    public function testFilterClosure(): void
    {
        static::assertSame('a', $this->runTwig('{{ ["a", "b", "c"]|filter(v => v == "a")|join }}'));
    }

    public function testFilterIteratorClosure(): void
    {
        static::assertSame(
            'a',
            $this->runTwig('{{ test|filter(v => v == "a")|join }}', [], ['test' => new \ArrayIterator(['a', 'b', 'c'])])
        );
    }

    public function testSortAllowedFunction(): void
    {
        set_error_handler(static function () {
            return true;
        });

        static::assertSame('abc', $this->runTwig('{{ ["a", "b", "c"]|sort("str_starts_with")|join }}', ['str_starts_with']));

        restore_error_handler();
    }

    public function testSortClosure(): void
    {
        static::assertSame('cba', $this->runTwig('{{ ["a", "b", "c"]|sort((a, b) => b <=> a)|join }}'));
    }

    public function testSortIteratorClosure(): void
    {
        static::assertSame(
            'cba',
            $this->runTwig('{{ test|sort((a, b) => b <=> a)|join }}', [], ['test' => new \ArrayIterator(['a', 'b', 'c'])])
        );
    }

    public function testSortDefault(): void
    {
        static::assertSame(
            '123',
            $this->runTwig('{{ test|sort|join }}', [], ['test' => ['2', '3', '1']])
        );
    }

    public function testAcceptsNull(): void
    {
        static::assertSame(
            '',
            $this->runTwig('{{ test|map(v => (v ~ "-test"))|join }}', [], ['test' => null])
        );
        static::assertSame(
            '',
            $this->runTwig('{{ test|reduce((a, b) => a + b)|join }}', [], ['test' => null])
        );
        static::assertSame(
            '',
            $this->runTwig('{{ test|filter(v => v == "a")|join }}', [], ['test' => null])
        );
        static::assertSame(
            '',
            $this->runTwig('{{ test|sort|join }}', [], ['test' => null])
        );
    }

    /**
     * @param array<string> $allowedFunctions
     * @param array<mixed> $variables
     */
    private function runTwig(string $template, array $allowedFunctions = [], array $variables = []): string
    {
        $twig = new Environment(new ArrayLoader([
            'test' => $template,
        ]));

        $twig->addExtension(new SecurityExtension($allowedFunctions));

        return $twig->render('test', $variables);
    }
}

/**
 * @internal
 *
 * Demonstrates that this static method cannot be called from Twig by closure
 */
class SecurityExtensionGadget
{
    public static function do(): void
    {
        throw new \Error('This should not be called');
    }
}

/**
 * @internal
 *
 * Demonstrates that closure can call this static method from Twig when allowed
 */
class SecurityExtensionGoodClass
{
    public static function upper(string $text): string
    {
        return strtoupper($text);
    }
}
