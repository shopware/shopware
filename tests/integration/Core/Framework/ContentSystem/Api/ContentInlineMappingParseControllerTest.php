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
            'text' => '{{map:category.description}}',
            'revision' => 7,
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

    public function testBatchPreservesIndependentResultsAndMatchesItsResponseSchema(): void
    {
        $browser = $this->getBrowser(permissions: ['content_layout:read']);
        $request = ['text' => '🙂 {{ map:product.name }}', 'revision' => 7];
        $browser->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse-batch', [
            'requests' => [
                $request,
                ['text' => '{{map:product.name}}' . str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES), 'revision' => 7],
                ['text' => '{{map:product}}', 'revision' => 0],
                $request,
            ],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $parsed = [
            'revision' => 7,
            'tokens' => [[
                'path' => 'product.name',
                'match' => '{{ map:product.name }}',
                'canonicalToken' => '{{map:product.name}}',
                'offset' => 5,
                'insideMarkup' => false,
                'malformed' => false,
            ]],
        ];
        $exception = ContentSystemException::inlineMappingInputTooLarge(InlineMappingTokenParser::MAX_INPUT_BYTES);
        static::assertSame(['results' => [
            $parsed,
            ['revision' => 7, 'error' => ['code' => $exception->getErrorCode(), 'message' => $exception->getMessage()]],
            ['revision' => 0, 'tokens' => [[
                'path' => 'product',
                'match' => '{{map:product}}',
                'canonicalToken' => null,
                'offset' => 0,
                'insideMarkup' => false,
                'malformed' => true,
            ]]],
            $parsed,
        ]], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));

        $schemaFile = file_get_contents(__DIR__ . '/../../../../../../src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-inline-mapping-parse-batch.json');
        static::assertNotFalse($schemaFile);
        $document = json_decode($schemaFile, true, 512, \JSON_THROW_ON_ERROR);
        $schema = Helper::toJSON($document['paths']['/_action/content-system/inline-mapping/parse-batch']['post']['responses']['200']['content']['application/json']['schema']);
        static::assertIsObject($schema);
        $result = (new Validator())->validate(json_decode((string) $response->getContent(), false, 512, \JSON_THROW_ON_ERROR), $schema);
        static::assertNull($result->error());
    }

    public function testBatchAcceptsFiftyEntries(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse-batch', [
            'requests' => array_fill(0, 50, ['text' => 'Plain text', 'revision' => 0]),
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(['results' => array_fill(0, 50, ['revision' => 0, 'tokens' => []])], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidBatchProvider')]
    public function testBatchRejectsInvalidRequests(array $payload): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse-batch', $payload);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testBatchRequiresTheLayoutReadPrivilege(): void
    {
        $browser = $this->getBrowser(permissions: []);
        $browser->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse-batch', [
            'requests' => [['text' => 'Plain text', 'revision' => 0]],
        ]);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidBatchProvider(): iterable
    {
        yield 'requests are required' => [[]];
        yield 'an empty batch is rejected' => [['requests' => []]];
        yield 'more than fifty fields are rejected' => [['requests' => array_fill(0, 51, ['text' => 'Text', 'revision' => 0])]];
        yield 'entries must form a list' => [['requests' => ['field' => ['text' => 'Text', 'revision' => 0]]]];
        yield 'unknown envelope attributes are rejected' => [['requests' => [['text' => 'Text', 'revision' => 0]], 'extra' => true]];
        yield 'unknown entry attributes are rejected' => [['requests' => [['text' => 'Text', 'revision' => 0, 'extra' => true]]]];
        yield 'negative nested revisions are rejected' => [['requests' => [['text' => 'Text', 'revision' => -1]]]];
        yield 'nested text is required' => [['requests' => [['revision' => 0]]]];
        yield 'null entries are rejected' => [['requests' => [null]]];
    }
}
