<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Kernel\EnvIntOrNullProcessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EnvIntOrNullProcessor::class)]
class EnvIntOrNullProcessorTest extends TestCase
{
    public function testReturnsNullForNullValue(): void
    {
        $processor = new EnvIntOrNullProcessor();

        $result = $processor->getEnv('int-or-null', 'FOO', static fn () => null);

        static::assertNull($result);
    }

    public function testReturnsNullForEmptyString(): void
    {
        $processor = new EnvIntOrNullProcessor();

        $result = $processor->getEnv('int-or-null', 'FOO', static fn () => '');

        static::assertNull($result);
    }

    public function testReturnsIntForNumericString(): void
    {
        $processor = new EnvIntOrNullProcessor();

        $result = $processor->getEnv('int-or-null', 'FOO', static fn () => '42');

        static::assertSame(42, $result);
    }

    public function testReturnsIntForZeroString(): void
    {
        $processor = new EnvIntOrNullProcessor();

        $result = $processor->getEnv('int-or-null', 'FOO', static fn () => '0');

        static::assertSame(0, $result);
    }

    public function testGetProvidedTypes(): void
    {
        static::assertSame(
            ['int-or-null' => 'int'],
            EnvIntOrNullProcessor::getProvidedTypes()
        );
    }
}
