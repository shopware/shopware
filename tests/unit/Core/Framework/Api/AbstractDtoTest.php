<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractDto::class)]
class AbstractDtoTest extends TestCase
{
    #[DataProvider('initializedValues')]
    public function testHasTracksPropertyInitialization(mixed $value): void
    {
        $dto = new class extends AbstractDto {
            public mixed $value;
        };

        static::assertFalse($dto->has('value'));
        static::assertFalse($dto->has('unknown'));

        $dto->value = $value;

        static::assertTrue($dto->has('value'));

        unset($dto->value);

        static::assertFalse($dto->has('value'));
    }

    public function testHasTracksInheritedExtensions(): void
    {
        $dto = new class extends AbstractDto {
        };

        static::assertFalse($dto->has('extensions'));

        $dto->addExtension('plugin', ['enabled' => false]);

        static::assertTrue($dto->has('extensions'));

        $dto->removeExtension('plugin');

        static::assertFalse($dto->has('extensions'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function initializedValues(): iterable
    {
        yield 'explicit null is present' => [null];
        yield 'false is present' => [false];
        yield 'integer zero is present' => [0];
        yield 'float zero is present' => [0.0];
        yield 'empty string is present' => [''];
        yield 'empty array is present' => [[]];
        yield 'nonempty value is present' => ['value'];
    }
}
