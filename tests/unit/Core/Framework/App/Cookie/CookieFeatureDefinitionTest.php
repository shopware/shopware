<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cookie\CookieConfig;
use Shopware\Core\Framework\App\Cookie\CookieFeatureDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(CookieFeatureDefinition::class)]
#[Package('framework')]
class CookieFeatureDefinitionTest extends TestCase
{
    private CookieFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new CookieFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('cookie', $this->definition->getType());
        static::assertSame(CookieConfig::class, $this->definition->getConfigClass());
    }

    public function testFromAppReturnsEmptyWhenNoCookies(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testFromAppReadsCookiesFromManifest(): void
    {
        $configs = $this->definition->fromApp(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest.xml'),
            new Filesystem(__DIR__),
            'en-GB',
        );

        static::assertCount(2, $configs);

        $single = $configs[0];
        static::assertSame('swag.analytics.name', $single->snippetName);
        static::assertSame('swag-analytics', $single->cookie);
        static::assertSame('', $single->value);
        static::assertSame(30, $single->expiration);
        static::assertSame([], $single->entries);

        $group = $configs[1];
        static::assertSame('app.cookies.group', $group->snippetName);
        static::assertSame('app.cookies.group.description', $group->snippetDescription);
        static::assertNull($group->cookie);
        static::assertCount(2, $group->entries);
        static::assertSame('swag-app-something', $group->entries[0]['cookie']);
        static::assertSame('first.cookie', $group->entries[0]['snippet_name']);
        static::assertSame('swag-app-lorem-ipsum', $group->entries[1]['cookie']);
    }

    public function testPayloadRoundTrip(): void
    {
        $declared = new CookieConfig(
            'app.cookies.group',
            'app.cookies.group.description',
            'the-cookie',
            'the-value',
            30,
            [['cookie' => 'sub-cookie', 'snippet_name' => 'sub.name']],
        );

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, null));

        static::assertEquals($declared, $hydrated);
    }
}
