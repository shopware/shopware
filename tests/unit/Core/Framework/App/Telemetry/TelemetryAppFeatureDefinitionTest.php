<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfig;
use Shopware\Core\Framework\App\Telemetry\TelemetryAppFeatureDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TelemetryAppFeatureDefinition::class)]
class TelemetryAppFeatureDefinitionTest extends TestCase
{
    private const MAPPINGS = [
        'entity_group' => ['product' => 'product', 'product_*' => 'product'],
        'flow_trigger_group' => [],
    ];

    private string $tmpDir;

    private SymfonyFilesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new SymfonyFilesystem();
        $this->tmpDir = sys_get_temp_dir() . '/sw-test-app-' . bin2hex(random_bytes(4));
        $this->fs->mkdir($this->tmpDir . '/Resources/config');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    public function testFromAppReturnsAcceptedClaimsAsOneConfig(): void
    {
        $this->fs->copy(__DIR__ . '/_fixtures/telemetry.xml', $this->tmpDir . '/Resources/config/telemetry.xml');

        $configs = $this->definition()->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');

        static::assertCount(1, $configs);
        static::assertSame(
            ['entity_group' => ['ce_blog_*', 'swag_blog_post'], 'flow_trigger_group' => ['swag.blog.published']],
            $configs[0]->mappings,
        );
    }

    public function testFromAppSkipsInvalidClaimsAndLogsOnce(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/Resources/config/telemetry.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <telemetry>
                <mapping name="unknown_mapping">
                    <key>swag_*</key>
                </mapping>
                <mapping name="entity_group">
                    <key>*</key>
                    <key>product</key>
                    <key>product_review</key>
                    <key>ce_blog_*</key>
                </mapping>
            </telemetry>
            XML);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::logicalAnd(
                static::stringContains('mapping "unknown_mapping" does not exist'),
                static::stringContains('a bare "*"'),
                static::stringContains('key "product" is declared in config'),
            ));

        $configs = $this->definition($logger)->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');

        // `product_review` is only shadowed by the declared prefix at lookup, so it is persisted as claimed
        static::assertCount(1, $configs);
        static::assertSame(['entity_group' => ['product_review', 'ce_blog_*']], $configs[0]->mappings);
    }

    public function testFromAppWithOnlyInvalidClaimsReturnsNothing(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/Resources/config/telemetry.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <telemetry>
                <mapping name="unknown_mapping">
                    <key>swag_*</key>
                </mapping>
            </telemetry>
            XML);

        $configs = $this->definition()->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');

        static::assertSame([], $configs);
    }

    public function testFromAppFailsOnUnparsableFile(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/Resources/config/telemetry.xml', 'not xml at all');

        $this->expectException(AppXmlParsingException::class);

        $this->definition()->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');
    }

    public function testFromAppWithoutFileReturnsNothing(): void
    {
        $configs = $this->definition()->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');

        static::assertSame([], $configs);
    }

    public function testPayloadRoundTripAndDeclarationWins(): void
    {
        $definition = $this->definition();
        $declared = new AppTelemetryConfig(['entity_group' => ['swag_*']]);
        $stored = new AppTelemetryConfig(['entity_group' => ['shop_edited_*']]);

        $payload = $definition->toPayload($declared, $stored);

        static::assertSame($declared->toArray(), $payload);
        static::assertSame($declared->mappings, $definition->fromPayload($payload)->mappings);
        static::assertSame(TelemetryAppFeatureDefinition::TYPE, $definition->getType());
        static::assertSame(AppTelemetryConfig::class, $definition->getConfigClass());
    }

    private function definition(?LoggerInterface $logger = null): TelemetryAppFeatureDefinition
    {
        return new TelemetryAppFeatureDefinition(self::MAPPINGS, $logger ?? new NullLogger());
    }

    private function createManifest(): Manifest
    {
        $metadata = static::createStub(Metadata::class);
        $metadata->method('getName')->willReturn('TestApp');

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);

        return $manifest;
    }
}
