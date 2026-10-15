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
                'canonicalToken' => '{{map:product.name}}',
                'offset' => 12,
                'insideMarkup' => false,
                'malformed' => false,
            ]],
        ], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testMarksMalformedMappingMarkersForTheEditorToLeaveAsText(): void
    {
        $controller = new ContentInlineMappingParseController(new InlineMappingTokenParser());

        $response = $controller->parse(new ContentInlineMappingParseRequest('Buy {{map:product}} today', 4));

        static::assertSame([
            'revision' => 4,
            'tokens' => [[
                'path' => 'product',
                'match' => '{{map:product}}',
                'canonicalToken' => '{{map:product}}',
                'offset' => 4,
                'insideMarkup' => false,
                'malformed' => true,
            ]],
        ], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testMarksMarkupBearingPathAsMalformed(): void
    {
        $controller = new ContentInlineMappingParseController(new InlineMappingTokenParser());

        $response = $controller->parse(new ContentInlineMappingParseRequest('{{map:x" onmouseover="alert(1)}}', 5));

        static::assertSame([
            'revision' => 5,
            'tokens' => [[
                'path' => 'x" onmouseover="alert(1)',
                'match' => '{{map:x" onmouseover="alert(1)}}',
                'canonicalToken' => '{{map:x" onmouseover="alert(1)}}',
                'offset' => 0,
                'insideMarkup' => false,
                'malformed' => true,
            ]],
        ], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }
}
