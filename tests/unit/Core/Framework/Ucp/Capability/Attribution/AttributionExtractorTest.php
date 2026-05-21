<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Attribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\Attribution\AttributionExtractor;

/**
 * @internal
 */
#[CoversClass(AttributionExtractor::class)]
class AttributionExtractorTest extends TestCase
{
    public function testNormalisesSpecAttributionFields(): void
    {
        $out = (new AttributionExtractor())->extract([
            'attribution' => [
                'campaign_id' => 'cmp_123',
                'campaign_source' => 'chatgpt',
                'campaign_medium' => 'agent',
                'campaign_name' => 'spring',
                'gclid' => 'gclid-1',
            ],
        ]);

        static::assertSame([
            'campaign_source' => 'chatgpt',
            'campaign_medium' => 'agent',
            'campaign_name' => 'spring',
            'campaign_id' => 'cmp_123',
            'gclid' => 'gclid-1',
        ], $out);
    }

    public function testAcceptsLegacyAliasesButOutputsSpecFieldNames(): void
    {
        $out = (new AttributionExtractor())->extract([
            'attribution' => [
                'source' => 'gemini',
                'medium' => 'agent',
                'campaign' => 'launch',
            ],
        ]);

        static::assertSame([
            'campaign_source' => 'gemini',
            'campaign_medium' => 'agent',
            'campaign_name' => 'launch',
        ], $out);
    }
}
