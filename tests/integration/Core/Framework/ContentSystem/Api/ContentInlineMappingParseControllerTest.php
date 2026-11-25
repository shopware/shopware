<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentInlineMappingParseControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testParsesAnInlineMappingToken(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse', [
            'text' => '{{map:category.description}}', 'revision' => 7,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame([
            'revision' => 7,
            'tokens' => [[
                'path' => 'category.description',
                'match' => '{{map:category.description}}',
                'canonicalToken' => '{{map:category.description}}',
                'offset' => 0,
                'insideMarkup' => false,
                'malformed' => false,
            ]],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testOnlyMalformedTokensHaveNoCanonicalText(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse', [
            'text' => '🙂 {{ map:product }} / {{map:AcmePlugin:unknown.title}} <a title="{{map:product.name}}">link</a>',
            'revision' => 8,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame([
            'revision' => 8,
            'tokens' => [
                [
                    'path' => 'product',
                    'match' => '{{ map:product }}',
                    'canonicalToken' => null,
                    'offset' => 5,
                    'insideMarkup' => false,
                    'malformed' => true,
                ],
                [
                    'path' => 'AcmePlugin:unknown.title',
                    'match' => '{{map:AcmePlugin:unknown.title}}',
                    'canonicalToken' => '{{map:AcmePlugin:unknown.title}}',
                    'offset' => 25,
                    'insideMarkup' => false,
                    'malformed' => false,
                ],
                [
                    'path' => 'product.name',
                    'match' => '{{map:product.name}}',
                    'canonicalToken' => '{{map:product.name}}',
                    'offset' => 68,
                    'insideMarkup' => true,
                    'malformed' => false,
                ],
            ],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testResponseMatchesItsSchema(): void
    {
        $browser = $this->getBrowser(permissions: ['content_layout:read']);
        $browser->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse', [
            'text' => '🙂 {{ map:product.name }}', 'revision' => 7,
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $schemaFile = file_get_contents(__DIR__ . '/../../../../../../src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-inline-mapping-parse.json');
        static::assertNotFalse($schemaFile);
        $document = json_decode($schemaFile, true, flags: \JSON_THROW_ON_ERROR);
        $schema = Helper::toJSON($document['paths']['/_action/content-system/inline-mapping/parse']['post']['responses']['200']['content']['application/json']['schema']);
        static::assertIsObject($schema);
        $result = (new Validator())->validate(json_decode((string) $response->getContent(), false, flags: \JSON_THROW_ON_ERROR), $schema);
        static::assertNull($result->error());
    }

    public function testRejectsOversizedTokenText(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse', [
            'text' => '{{map:product.name}}' . str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES),
            'revision' => 0,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(ContentSystemException::INLINE_MAPPING_INPUT_TOO_LARGE, $body['errors'][0]['code']);
    }

    public function testAcceptsLongPlainTextWithoutMappingSyntax(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse', [
            'text' => str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES + 1), 'revision' => 2,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(['revision' => 2, 'tokens' => []], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidRequestProvider')]
    public function testRejectsInvalidRequests(array $payload): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse', $payload);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testRequiresTheLayoutReadPrivilege(): void
    {
        $browser = $this->getBrowser(permissions: []);
        $browser->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse', ['text' => 'Plain text', 'revision' => 0]);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidRequestProvider(): iterable
    {
        yield 'text is required' => [['revision' => 0]];
        yield 'revision is required' => [['text' => 'Plain text']];
        yield 'negative revisions are rejected' => [['text' => 'Plain text', 'revision' => -1]];
        yield 'null text is rejected' => [['text' => null, 'revision' => 0]];
        yield 'structured text is rejected' => [['text' => ['nested' => 'Plain text'], 'revision' => 0]];
        yield 'unknown attributes are rejected' => [['text' => 'Plain text', 'revision' => 0, 'extra' => true]];
    }
}
