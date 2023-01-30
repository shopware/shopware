<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Twig\SecurityExtension
 */
class SecurityExtensionTest extends TestCase
{
    public function testMapNotAllowedFunction(): void
    {
        $this->expectException(RuntimeError::class);
        $this->runTwig('{{ ["a", "b", "c"]|map("str_rot13")|join }}');
    }

    public function testMapWithAllowedFunction(): void
    {
        static::assertSame('nop', $this->runTwig('{{ ["a", "b", "c"]|map("str_rot13")|join }}', ['str_rot13']));
    }

    public function testMapWithClosure(): void
    {
        static::assertSame('a-testb-testc-test', $this->runTwig('{{ ["a", "b", "c"]|map(v => (v ~ "-test"))|join }}'));
    }

    public function testReduceNotAllowedFunction(): void
    {
        $this->expectException(RuntimeError::class);
        $this->runTwig('{{ ["a", "b", "c"]|reduce("empty")|join }}');
    }

    public function testReduceAllowedFunction(): void
    {
        static::assertSame('6', $this->runTwig('{{ [1 , 5]|reduce((a, b) => a + b)|json_encode|raw }}'));
    }

    public function testReduceOnIterator(): void
    {
        static::assertSame('3', $this->runTwig('{{ test|reduce((a, b) => a + b)|json_encode|raw }}', [], ['test' => new \ArrayIterator([1, 2])]));
    }

    public function testFilterNotAllowedFunctionWithAllowedFunction(): void
    {
        $this->expectException(RuntimeError::class);
        $this->runTwig('{{ ["a", "b", "c"]|filter("str_rot13")|join }}');
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

    public function testSortNotAllowedFunction(): void
    {
        $this->expectException(RuntimeError::class);
        $this->runTwig('{{ ["a", "b", "c"]|sort("str_rot13")|join }}');
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
