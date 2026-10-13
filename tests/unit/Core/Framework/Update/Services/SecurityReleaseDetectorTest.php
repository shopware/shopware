<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Services\SecurityReleaseDetector;
use Shopware\Core\Framework\Update\Struct\Version;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SecurityReleaseDetector::class)]
class SecurityReleaseDetectorTest extends TestCase
{
    public function testDetectReturnsNoSecurityReleaseWhenVulnerabilitiesAreEmptyAndNoFallback(): void
    {
        $detector = new SecurityReleaseDetector();
        $version = new Version(['version' => '6.2.0', 'date' => '2020-01-01']);

        $securityRelease = $detector->detect($version);

        static::assertFalse($securityRelease->isSecurityRelease);
        static::assertSame('', $securityRelease->highestSeverity);
        static::assertSame(0, $securityRelease->totalVulnerabilities);
    }

    public function testDetectIdentifiesSecurityReleaseByFixedVulnerabilities(): void
    {
        $detector = new SecurityReleaseDetector();
        $version = new Version([
            'version' => '6.2.0',
            'date' => '2020-01-01',
            'fixedVulnerabilities' => [
                ['severity' => 'high', 'summary' => 'SQL injection', 'link' => 'https://example.com'],
            ],
        ]);

        $securityRelease = $detector->detect($version);

        static::assertTrue($securityRelease->isSecurityRelease);
        static::assertSame('high', $securityRelease->highestSeverity);
        static::assertSame(1, $securityRelease->totalVulnerabilities);
    }

    public function testDetectPicksHighestSeverity(): void
    {
        $detector = new SecurityReleaseDetector();
        $version = new Version([
            'version' => '6.2.0',
            'date' => '2020-01-01',
            'fixedVulnerabilities' => [
                ['severity' => 'low', 'summary' => 'Minor', 'link' => 'https://example.com'],
                ['severity' => 'critical', 'summary' => 'RCE', 'link' => 'https://example.com'],
                ['severity' => 'medium', 'summary' => 'XSS', 'link' => 'https://example.com'],
            ],
        ]);

        $securityRelease = $detector->detect($version);

        static::assertSame('critical', $securityRelease->highestSeverity);
        static::assertSame(3, $securityRelease->totalVulnerabilities);
    }

    public function testDetectFallbackOnSecurityKeyword(): void
    {
        $detector = new SecurityReleaseDetector();
        $version = new Version([
            'version' => '6.2.0',
            'date' => '2020-01-01',
            'body' => 'This release fixes a security issue.',
        ]);

        $securityRelease = $detector->detect($version);

        static::assertTrue($securityRelease->isSecurityRelease);
        static::assertSame('unknown', $securityRelease->highestSeverity);
    }

    public function testDetectFallbackOnCveIdentifier(): void
    {
        $detector = new SecurityReleaseDetector();
        $version = new Version([
            'version' => '6.2.0',
            'date' => '2020-01-01',
            'title' => 'Fixes CVE-2026-12345',
        ]);

        $securityRelease = $detector->detect($version);

        static::assertTrue($securityRelease->isSecurityRelease);
        static::assertSame('unknown', $securityRelease->highestSeverity);
    }
}
