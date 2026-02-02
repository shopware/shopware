<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FlagMetadata;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FlagMetadata::class)]
final class FlagMetadataTest extends TestCase
{
    /**
     * @param class-string<Flag> $flagClass
     * @param list<mixed> $args
     */
    #[DataProvider('constructionProvider')]
    public function testConstruction(string $flagClass, array $args): void
    {
        $metadata = new FlagMetadata($flagClass, $args);

        static::assertSame($flagClass, $metadata->flagClass);
        static::assertSame($args, $metadata->args);
    }

    /**
     * @return \Generator<string, array{flagClass: class-string<Flag>, args: list<mixed>}>
     */
    public static function constructionProvider(): \Generator
    {
        yield 'without args' => [
            'flagClass' => Required::class,
            'args' => [],
        ];

        yield 'with args' => [
            'flagClass' => Inherited::class,
            'args' => ['foreignKey'],
        ];
    }

    public function testInvalidFlagClassThrowsException(): void
    {
        $this->expectException(DataAbstractionLayerException::class);
        $this->expectExceptionMessage('FlagMetadata requires a Flag subclass, got "InvalidFlag".');

        new FlagMetadata('InvalidFlag'); // @phpstan-ignore argument.type
    }

    /**
     * @param class-string<Flag> $flagClass
     * @param list<mixed> $args
     * @param class-string<Flag> $expectedInstance
     */
    #[DataProvider('createFlagProvider')]
    public function testCreateFlag(string $flagClass, array $args, string $expectedInstance): void
    {
        $metadata = new FlagMetadata($flagClass, $args);

        $flag = $metadata->createFlag();

        static::assertInstanceOf($expectedInstance, $flag);
    }

    /**
     * @return \Generator<string, array{flagClass: class-string<Flag>, args: list<mixed>, expectedInstance: class-string<Flag>}>
     */
    public static function createFlagProvider(): \Generator
    {
        yield 'without args' => [
            'flagClass' => Required::class,
            'args' => [],
            'expectedInstance' => Required::class,
        ];

        yield 'with args' => [
            'flagClass' => Inherited::class,
            'args' => ['custom_fk'],
            'expectedInstance' => Inherited::class,
        ];

        yield 'api aware with sources' => [
            'flagClass' => ApiAware::class,
            'args' => [AdminApiSource::class, SalesChannelApiSource::class],
            'expectedInstance' => ApiAware::class,
        ];
    }

    /**
     * @param class-string<Flag> $flagClass
     * @param list<mixed> $args
     */
    #[DataProvider('toDefinitionProvider')]
    public function testToDefinition(string $flagClass, array $args): void
    {
        $metadata = new FlagMetadata($flagClass, $args);

        $definition = $metadata->toDefinition();

        static::assertSame(FlagMetadata::class, $definition->getClass());

        $definitionArgs = $definition->getArguments();
        static::assertCount(2, $definitionArgs);
        static::assertSame($flagClass, $definitionArgs[0]);
        static::assertSame($args, $definitionArgs[1]);
    }

    /**
     * @return \Generator<string, array{flagClass: class-string<Flag>, args: list<mixed>}>
     */
    public static function toDefinitionProvider(): \Generator
    {
        yield 'with args' => [
            'flagClass' => Inherited::class,
            'args' => ['custom_fk'],
        ];

        yield 'empty args' => [
            'flagClass' => PrimaryKey::class,
            'args' => [],
        ];
    }
}
