<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingMetadata;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingToken;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappingMetadata::class)]
class InlineMappingMetadataTest extends TestCase
{
    public function testReturnsOnlyTheRequestedElementsPropertyTokens(): void
    {
        $first = new InlineMappingToken('product.name', '{{map:product.name}}', 0);
        $second = new InlineMappingToken('product.description', '{{map:product.description}}', 25);
        $metadata = new InlineMappingMetadata([
            'element-1' => ['text' => [$first, $second], 'title' => [$second]],
            'element-2' => ['text' => [$second]],
        ]);

        static::assertSame([$first, $second], $metadata->tokensFor('element-1', 'text'));
        static::assertSame([$second], $metadata->tokensFor('element-1', 'title'));
        static::assertSame([$second], $metadata->tokensFor('element-2', 'text'));
        static::assertSame([], $metadata->tokensFor('missing-element', 'text'));
        static::assertSame([], $metadata->tokensFor('element-1', 'missing-property'));
        static::assertSame([], (new InlineMappingMetadata())->tokensFor('element-1', 'text'));
    }
}
