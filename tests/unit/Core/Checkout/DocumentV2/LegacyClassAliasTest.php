<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\SalesChannel\AbstractDocumentRoute;
use Shopware\Core\Checkout\DocumentV2\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversNothing]
class LegacyClassAliasTest extends TestCase
{
    private const DOCUMENT = 'Shopware\\Core\\Checkout\\Document\\';

    private const BASE_CONFIG = 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\\';

    private const BASE_CONFIG_SALES_CHANNEL = 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\\';

    private const SALES_CHANNEL = 'Shopware\Core\Checkout\Document\SalesChannel\\';

    /**
     * @return iterable<string, array{legacy: string, current: class-string}>
     */
    public static function legacyClassAliasProvider(): iterable
    {
        yield 'RenderedDocument' => [
            'legacy' => self::DOCUMENT . 'Renderer\\RenderedDocument',
            'current' => RenderedDocument::class,
        ];

        yield 'DocumentEntity' => [
            'legacy' => self::DOCUMENT . 'DocumentEntity',
            'current' => DocumentEntity::class,
        ];

        yield 'DocumentDefinition' => [
            'legacy' => self::DOCUMENT . 'DocumentDefinition',
            'current' => DocumentDefinition::class,
        ];

        yield 'DocumentCollection' => [
            'legacy' => self::DOCUMENT . 'DocumentCollection',
            'current' => DocumentCollection::class,
        ];

        yield 'DocumentBaseConfigEntity' => [
            'legacy' => self::BASE_CONFIG . 'DocumentBaseConfigEntity',
            'current' => DocumentBaseConfigEntity::class,
        ];

        yield 'DocumentBaseConfigDefinition' => [
            'legacy' => self::BASE_CONFIG . 'DocumentBaseConfigDefinition',
            'current' => DocumentBaseConfigDefinition::class,
        ];

        yield 'DocumentBaseConfigCollection' => [
            'legacy' => self::BASE_CONFIG . 'DocumentBaseConfigCollection',
            'current' => DocumentBaseConfigCollection::class,
        ];

        yield 'DocumentBaseConfigSalesChannelEntity' => [
            'legacy' => self::BASE_CONFIG_SALES_CHANNEL . 'DocumentBaseConfigSalesChannelEntity',
            'current' => DocumentBaseConfigSalesChannelEntity::class,
        ];

        yield 'DocumentBaseConfigSalesChannelDefinition' => [
            'legacy' => self::BASE_CONFIG_SALES_CHANNEL . 'DocumentBaseConfigSalesChannelDefinition',
            'current' => DocumentBaseConfigSalesChannelDefinition::class,
        ];

        yield 'DocumentBaseConfigSalesChannelCollection' => [
            'legacy' => self::BASE_CONFIG_SALES_CHANNEL . 'DocumentBaseConfigSalesChannelCollection',
            'current' => DocumentBaseConfigSalesChannelCollection::class,
        ];

        yield 'AbstractDocumentRoute' => [
            'legacy' => self::SALES_CHANNEL . 'AbstractDocumentRoute',
            'current' => AbstractDocumentRoute::class,
        ];

        yield 'DocumentRoute' => [
            'legacy' => self::SALES_CHANNEL . 'DocumentRoute',
            'current' => DocumentRoute::class,
        ];
    }

    #[DataProvider('legacyClassAliasProvider')]
    public function testPreviousNameIsAnAliasAndNotASubclass(string $legacy, string $current): void
    {
        static::assertTrue(is_a($current, $legacy, allow_string: true));
        static::assertTrue(is_a($legacy, $current, allow_string: true));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPreviousNameResolvesWithoutBeingNamedFirst(): void
    {
        foreach (self::legacyClassAliasProvider() as $name => $case) {
            static::assertFalse(class_exists($case['legacy'], autoload: false), $name);

            static::assertTrue(class_exists($case['current']), $name);
            static::assertTrue(class_exists($case['legacy'], autoload: false), $name);
        }
    }

    #[DataProvider('instantiableLegacyClassAliasProvider')]
    public function testPreviousNameSatisfiesInstanceof(string $legacy, string $current): void
    {
        $instance = new $current();

        // @phpstan-ignore argument.type (the previous name exists only as a runtime alias, so it is not a class-string)
        static::assertInstanceOf($legacy, $instance);
    }

    #[DataProvider('legacyClassAliasProvider')]
    public function testShimFileStaysAtThePreviousPath(string $legacy, string $current): void
    {
        $path = \dirname(__DIR__, 5) . '/src/' . str_replace(
            ['Shopware\\Core\\', '\\'],
            ['Core/', '/'],
            $legacy,
        ) . '.php';

        static::assertFileExists($path);
    }

    /**
     * @return iterable<string, array{legacy: string, current: class-string}>
     */
    public static function instantiableLegacyClassAliasProvider(): iterable
    {
        foreach (self::legacyClassAliasProvider() as $name => $case) {
            $reflection = new \ReflectionClass($case['current']);

            if (!$reflection->isInstantiable()) {
                continue;
            }

            if (($reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
                continue;
            }

            yield $name => $case;
        }
    }
}
