<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\ContextWiringDecoder;
use Shopware\Core\Framework\Log\Package;

/**
 * The element-local wiring tier of decode. Every test here reaches the rules through
 * `StoredElementCodec::decode()`, which is the only entry point the composing codec exposes.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextWiringDecoder::class)]
class ContextWiringDecoderTest extends StoredElementCodecTestCase
{
    /**
     * @param array<string, mixed> $wire
     * @param list<string> $expectedConsumerKeys
     */
    #[DataProvider('acceptsCleanElementWiringProvider')]
    #[TestDox('decode accepts $_dataName')]
    public function testDecodeAcceptsACleanElementWiringSibling(array $wire, array $expectedConsumerKeys): void
    {
        $element = $this->codec()->decode($wire);

        static::assertSame($expectedConsumerKeys, array_keys($element->contextDefinitions->getAllConsumers()));
    }

    /**
     * The element-local wiring tier: a consumer map judged against itself, and against the element's own
     * provider map. Each row names the rule's own exception, and each has a sibling in
     * {@see acceptsCleanElementWiringProvider()} one edit away on the tested axis alone.
     *
     * The error code is asserted explicitly: `expectExceptionObject()` compares `getCode()`, which Symfony's
     * HttpException leaves at 0 for every ContentSystemException, so it alone would not tell these three apart.
     *
     * @param array<string, mixed> $wire
     */
    #[DataProvider('rejectsElementLocalWiringProvider')]
    #[TestDox('decode rejects $_dataName')]
    public function testDecodeRejectsAnElementLocalWiringDefect(array $wire, ContentSystemException $expected): void
    {
        try {
            $this->codec()->decode($wire);
            static::fail('Expected decode to reject the element-local wiring defect.');
        } catch (ContentSystemException $exception) {
            static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
            static::assertSame($expected->getMessage(), $exception->getMessage());
        }
    }

    /**
     * The check order decode owes: the per-consumer combination tier finishes across the whole consumer map
     * before the element-local tier starts, and within the element-local tier the rules run in the declared
     * order. Every row below violates two rules at once, so the exception identifies which one decode reached
     * first; the descriptor reports both, which its own test pins.
     *
     * @param array<string, mixed> $wire
     */
    #[DataProvider('pinsElementWiringCheckOrderProvider')]
    #[TestDox('decode throws $_dataName')]
    public function testDecodeThrowsTheFirstRuleInTheDeclaredOrder(array $wire, ContentSystemException $expected): void
    {
        try {
            $this->codec()->decode($wire);
            static::fail('Expected decode to reject the doubly defective element.');
        } catch (ContentSystemException $exception) {
            static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
            static::assertSame($expected->getMessage(), $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function rejectsElementLocalWiringProvider(): iterable
    {
        yield 'two consumers landing on one base key' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product'],
            ]]),
            ContentSystemException::propertyAliasCollision('product', 'product', 'category'),
        ];

        yield 'a redistributing consumer keyed by a dotted path' => [
            self::baseWire(['acceptsContext' => [
                'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]]),
            ContentSystemException::redistributeWithDottedPath('product.manufacturer'),
        ];

        yield 'a redistributing consumer whose context key an authored provider holds' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ]),
            ContentSystemException::redistributeConflict('product'),
        ];

        // The derived provider key is what the consumer writes, so a propertyAlias moves the collision onto
        // the alias: a rule judged on the context key alone would let this one through.
        yield 'a redistributing consumer whose property alias an authored provider holds' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'source' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'propertyAlias' => 'product'],
                ],
            ]),
            ContentSystemException::redistributeConflict('source'),
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function acceptsCleanElementWiringProvider(): iterable
    {
        yield 'two consumers landing on distinct base keys' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'item'],
            ]]),
            ['product', 'category'],
        ];

        yield 'a redistributing consumer keyed by a base key' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]]),
            ['product'],
        ];

        yield 'a redistributing consumer beside a provider on another key' => [
            self::baseWire([
                'providesContext' => ['other' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ]),
            ['product'],
        ];

        yield 'a redistributing consumer whose property alias no provider holds' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'source' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'propertyAlias' => 'productList'],
                ],
            ]),
            ['source'],
        ];
        // A consumerAlias equal to an authored provider key is deliberately absent: rejectInvalidElementWiring
        // reads propertyAlias and redistribute only, so such a row takes the same path as the one above it, and
        // the consumerAlias-with-redistribute branch is already covered by roundTripProvider's every-field row.
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function pinsElementWiringCheckOrderProvider(): iterable
    {
        // One consumer violating both tiers at once: it renames without redistributing, and it lands on the
        // base key an earlier consumer already holds.
        yield 'the combination rule for a consumer violating a combination rule and a cross-map rule at once' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => [
                    'type' => 'single',
                    'required' => true,
                    'consumerAlias' => 'inner',
                    'propertyAlias' => 'product',
                ],
            ]]),
            ContentSystemException::consumerAliasWithoutRedistribute('category'),
        ];

        // Both violations sit inside the element-local tier, so the declared order within it decides:
        // landing-key uniqueness before the dotted redistribute key.
        yield 'the landing-key rule for an element violating two element-local rules' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]]),
            ContentSystemException::propertyAliasCollision('product', 'product', 'product.manufacturer'),
        ];

        // The remaining pair inside the element-local tier, on one consumer: its key is dotted and the key it
        // would derive is one an authored provider holds. Reordering the two checks inside the redistribute
        // loop is what this row catches, and nothing else does.
        yield 'the dotted-key rule for a consumer violating both redistribute rules at once' => [
            self::baseWire([
                'providesContext' => ['product.manufacturer' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                ],
            ]),
            ContentSystemException::redistributeWithDottedPath('product.manufacturer'),
        ];

        // The two element-local rules on disjoint consumers, which is what pins the order of the two loops
        // rather than the order of the checks inside one of them.
        yield 'the landing-key rule over a provider conflict on a different consumer' => [
            self::baseWire([
                'providesContext' => ['shared' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'product' => ['type' => 'single', 'required' => true],
                    'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product'],
                    'shared' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                ],
            ]),
            ContentSystemException::propertyAliasCollision('product', 'product', 'category'),
        ];

        // Tier-major, not consumer-major: the earlier consumer's element-local violation waits for the whole
        // combination tier, so the later consumer's combination violation is what decode reports.
        yield 'the combination rule for a later consumer over an earlier cross-map violation' => [
            self::baseWire([
                'providesContext' => ['early' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'early' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                    'late' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'nested.key'],
                ],
            ]),
            ContentSystemException::propertyAliasWithDotNotation('late', 'nested.key'),
        ];
    }
}
