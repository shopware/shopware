<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippet;
use Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippetCollection;

/**
 * @covers \Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippetCollection
 *
 * @internal
 */
class CustomSnippetCollectionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $snippets = [
            CustomSnippet::createPlain('~'),
            CustomSnippet::createSnippet('@Framework/snippets/address/first_name.html.twig'),
        ];

        $collection = new CustomSnippetCollection($snippets);

        static::assertEquals(CustomSnippet::class, $collection->getExpectedClass());
        static::assertSame([
            [
                'type' => 'plain',
                'value' => '~',
            ],
            [
                'type' => 'snippet',
                'value' => '@Framework/snippets/address/first_name.html.twig',
            ],
        ], $collection->toArray());
    }
}
