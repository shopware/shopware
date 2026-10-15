<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentInlineMappingParseController;
use Shopware\Core\Framework\ContentSystem\Api\ContentInlineMappingParseRequest;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentInlineMappingParseController::class)]
class ContentInlineMappingParseControllerTest extends TestCase
{
    public function testReturnsServerParsedTokenSpansAndEchoesTheRevision(): void
    {
        $controller = new ContentInlineMappingParseController(new InlineMappingTokenParser());

        $response = $controller->parse(new ContentInlineMappingParseRequest(
            '<p>🙂 Buy {{ map:product.name }}.</p>',
            17,
        ));

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'revision' => 17,
            'tokens' => [[
                'path' => 'product.name',
                'match' => '{{ map:product.name }}',
                'offset' => 12,
                'insideMarkup' => false,
            ]],
        ], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }
}
