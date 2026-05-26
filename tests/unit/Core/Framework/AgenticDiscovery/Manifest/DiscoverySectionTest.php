<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\DiscoverySection;

/**
 * @internal
 */
#[CoversClass(DiscoverySection::class)]
class DiscoverySectionTest extends TestCase
{
    public function testExposesTitleBodyPriority(): void
    {
        $section = new DiscoverySection('Returns', 'We accept returns within 30 days.', 42);

        static::assertSame('Returns', $section->getTitle());
        static::assertSame('We accept returns within 30 days.', $section->getBody());
        static::assertSame(42, $section->getPriority());
    }

    public function testPriorityDefaultsToZero(): void
    {
        $section = new DiscoverySection('Brand', 'Calm.');

        static::assertSame(0, $section->getPriority());
    }

    public function testHasStableApiAlias(): void
    {
        $section = new DiscoverySection('x', 'y');

        static::assertSame('agentic_discovery_section', $section->getApiAlias());
    }
}
