<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoveryDocumentType::class)]
class AgenticDiscoveryDocumentTypeTest extends TestCase
{
    public function testEnumExposesAllFourDocuments(): void
    {
        $values = array_map(static fn (AgenticDiscoveryDocumentType $t): string => $t->value, AgenticDiscoveryDocumentType::cases());

        static::assertCount(4, $values);
        static::assertContains('agents.md', $values);
        static::assertContains('llms.txt', $values);
        static::assertContains('llms-full.txt', $values);
        static::assertContains('sitemap_agentic_discovery.xml', $values);
    }

    public function testEnumCanBeConstructedFromString(): void
    {
        static::assertSame(AgenticDiscoveryDocumentType::AGENTS_MD, AgenticDiscoveryDocumentType::from('agents.md'));
        static::assertSame(AgenticDiscoveryDocumentType::LLMS_TXT, AgenticDiscoveryDocumentType::from('llms.txt'));
        static::assertSame(AgenticDiscoveryDocumentType::LLMS_FULL_TXT, AgenticDiscoveryDocumentType::from('llms-full.txt'));
        static::assertSame(AgenticDiscoveryDocumentType::AGENTIC_SITEMAP, AgenticDiscoveryDocumentType::from('sitemap_agentic_discovery.xml'));
    }
}
