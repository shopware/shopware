<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Struct\Version;

/**
 * @internal
 */
#[CoversClass(Version::class)]
class VersionTest extends TestCase
{
    public function testEmptyConstructor(): void
    {
        $version = new Version();
        static::assertSame('', $version->title);
        static::assertSame('', $version->body);
        static::assertSame('', $version->version);
        static::assertSame([], $version->fixedVulnerabilities);

        static::assertSame('update_api_version', $version->getApiAlias());
    }

    public function testFillConstructor(): void
    {
        $vuln = ['severity' => 'severity', 'summary' => 'summary', 'link' => 'link'];
        $version = new Version([
            'title' => 'title',
            'body' => 'body',
            'version' => 'version',
            'fixedVulnerabilities' => [$vuln],
            'date' => '2020-01-01',
        ]);

        static::assertSame('title', $version->title);
        static::assertSame('body', $version->body);
        static::assertSame('version', $version->version);
        static::assertSame([$vuln], $version->fixedVulnerabilities);
    }
}
