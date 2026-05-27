<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Attribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\Attribution\AttributionExtractor;

/**
 * Pins the alias-normalisation table and the medium enum allowlist. Tests
 * named `…BehaviourTest` to avoid collision with the existing roundtrip
 * suite while still being discovered by PHPUnit.
 *
 * @internal
 */
#[CoversClass(AttributionExtractor::class)]
class AttributionExtractorBehaviourTest extends TestCase
{
    public function testReturnsNullWhenAttributionKeyIsMissing(): void
    {
        static::assertNull((new AttributionExtractor())->extract([]));
    }

    public function testReturnsNullWhenAttributionIsEmptyArray(): void
    {
        static::assertNull((new AttributionExtractor())->extract(['attribution' => []]));
    }

    public function testReturnsNullWhenAttributionContainsOnlyUnknownFields(): void
    {
        static::assertNull(
            (new AttributionExtractor())->extract(['attribution' => ['nope' => 'value']])
        );
    }

    public function testCanonicalisesShortAliasesToSpecFieldNames(): void
    {
        $out = $this->extractOrFail([
            'source' => 'chatgpt',
            'medium' => 'agent',
            'campaign' => 'spring-promo',
        ]);

        static::assertSame('chatgpt', $out['campaign_source']);
        static::assertSame('agent', $out['campaign_medium']);
        static::assertSame('spring-promo', $out['campaign_name']);
    }

    public function testNormalisesUtmAliases(): void
    {
        $out = $this->extractOrFail([
            'utm_source' => 'perplexity',
            'utm_medium' => 'agent',
            'utm_campaign' => 'q2',
            'utm_id' => 'c-1',
        ]);

        static::assertSame('perplexity', $out['campaign_source']);
        static::assertSame('c-1', $out['campaign_id']);
    }

    public function testStripsUnknownMediumValues(): void
    {
        $out = $this->extractOrFail([
            'source' => 'x',
            'medium' => 'something-totally-made-up',
        ]);

        static::assertArrayHasKey('campaign_source', $out);
        static::assertArrayNotHasKey('campaign_medium', $out);
    }

    public function testPassesThroughClickIds(): void
    {
        $out = $this->extractOrFail([
            'source' => 'x',
            'gclid' => 'gcl-1',
            'fbclid' => 'fb-1',
            'msclkid' => 'ms-1',
        ]);

        static::assertSame('gcl-1', $out['gclid']);
        static::assertSame('fb-1', $out['fbclid']);
        static::assertSame('ms-1', $out['msclkid']);
    }

    public function testKeepsValidReferrerUrl(): void
    {
        $out = $this->extractOrFail([
            'source' => 'x',
            'referrer_url' => 'https://chat.example/conversation/abc',
        ]);

        static::assertSame('https://chat.example/conversation/abc', $out['referrer_url']);
    }

    public function testDropsInvalidReferrerUrl(): void
    {
        $out = $this->extractOrFail([
            'source' => 'x',
            'referrer_url' => 'definitely-not-a-url',
        ]);

        static::assertArrayNotHasKey('referrer_url', $out);
    }

    public function testPassesThroughOpaqueAgentIdentifiers(): void
    {
        $out = $this->extractOrFail([
            'source' => 'x',
            'agent_session_id' => 'sess-1',
            'agent_user_id' => 'user-x',
        ]);

        static::assertSame('sess-1', $out['agent_session_id']);
        static::assertSame('user-x', $out['agent_user_id']);
    }

    public function testIgnoresEmptyStringValuesEvenIfKeyIsKnown(): void
    {
        $out = $this->extractOrFail([
            'source' => '',
            'medium' => 'agent',
        ]);

        // empty `source` did not populate campaign_source
        static::assertArrayNotHasKey('campaign_source', $out);
        // medium still picked up, so we still get *some* output
        static::assertSame('agent', $out['campaign_medium']);
    }

    /**
     * @param array<string, mixed> $attribution
     *
     * @return array<string, mixed>
     */
    private function extractOrFail(array $attribution): array
    {
        $out = (new AttributionExtractor())->extract(['attribution' => $attribution]);
        static::assertNotNull($out, 'AttributionExtractor returned null — test expected at least one normalised field.');

        return $out;
    }
}
