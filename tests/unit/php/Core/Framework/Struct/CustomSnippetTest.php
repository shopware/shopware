<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippet;

/**
 * @covers \Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippet
 *
 * @internal
 */
class CustomSnippetTest extends TestCase
{
    public function testInstantiatePlainSnippet(): void
    {
        $plain = CustomSnippet::createPlain('@');

        static::assertEquals(CustomSnippet::PLAIN_TYPE, $plain->getType());
        static::assertEquals('@', $plain->getValue());
    }

    public function testInstantiateSnippet(): void
    {
        $plain = CustomSnippet::createSnippet('@Framework/snippets/address/first_name.html.twig');

        static::assertEquals(CustomSnippet::SNIPPET_TYPE, $plain->getType());
        static::assertEquals('@Framework/snippets/address/first_name.html.twig', $plain->getValue());
    }
}
