<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Consent\ConsentConfig;
use Shopware\Core\Framework\App\Consent\ConsentFeatureDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConsentFeatureDefinition::class)]
class ConsentFeatureDefinitionTest extends TestCase
{
    private ConsentFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new ConsentFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('consent', $this->definition->getType());
        static::assertSame(ConsentConfig::class, $this->definition->getConfigClass());
    }

    public function testFromAppReturnsEmptyWhenNoConsents(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testFromAppReadsConsentsFromManifest(): void
    {
        $configs = $this->definition->fromApp(
            Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml'),
            new Filesystem(__DIR__),
            'en-GB',
        );

        static::assertCount(2, $configs);

        $first = $configs[0];
        static::assertSame('order_analysis', $first->name);
        static::assertSame('system', $first->scope);
        static::assertSame('2026-01-01', $first->revision);

        $second = $configs[1];
        static::assertSame('usage_tracking', $second->name);
        static::assertSame('admin_user', $second->scope);
        static::assertNull($second->revision);
    }

    public function testPayloadRoundTripIgnoresStored(): void
    {
        $declared = new ConsentConfig('order_analysis', 'system', '2026-01-01');
        $stored = new ConsentConfig('order_analysis', 'admin_user', '2025-01-01');

        $payload = $this->definition->toPayload($declared, $stored);
        $hydrated = $this->definition->fromPayload($payload);

        static::assertEquals($declared, $hydrated);
    }
}
