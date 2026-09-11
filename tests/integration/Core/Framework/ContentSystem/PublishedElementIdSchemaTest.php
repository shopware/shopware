<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * The unit-level conformance test compares the pattern string against decode. This one puts real payloads
 * through the document the API actually publishes, so what is asserted is composition in the generated
 * document rather than the text the source file holds.
 *
 * It covers the three things the source file alone cannot show: that `$ref` resolves in the generated
 * document, that `allOf` composition keeps the referenced pattern in force alongside a sibling description,
 * and that the `anyOf` nullable form still admits `null`.
 *
 * What it deliberately does NOT establish is the ECMA-262 verdict a client reaches. `opis/json-schema`
 * compiles a `pattern` to PCRE (`Helper::patternToRegex()` appends `uD` and hands it to `preg_match`), and
 * PCRE's `.` excludes only `\n` where ECMA's excludes four code points. So this validator agrees with a
 * browser on `hero\n` and parts from it on `hero\r`. The unit test owns that axis, by translating the
 * pattern; rows here stay on inputs where the two engines cannot disagree.
 *
 * @internal
 */
#[Package('framework')]
class PublishedElementIdSchemaTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const DOCUMENT_URI = 'https://shopware.test/schema/admin-api.json';

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('payloadProvider')]
    #[TestDox('$_dataName')]
    public function testTheGeneratedDocumentJudgesThePayload(string $schemaName, array $payload, bool $valid): void
    {
        $result = $this->validator()->validate(
            self::asJsonValue($payload),
            self::DOCUMENT_URI . '#/components/schemas/' . $schemaName
        );

        static::assertSame($valid, $result->isValid(), self::explain($result));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, bool}>
     */
    public static function payloadProvider(): iterable
    {
        yield 'a stored element with an author-supplied id is accepted' => [
            'StoredContentElement',
            ['id' => 'el-1', 'component' => 'core:text'],
            true,
        ];

        yield 'a stored element whose id carries a line feed is refused' => [
            'StoredContentElement',
            ['id' => "hero\nfoot", 'component' => 'core:text'],
            false,
        ];

        yield 'a stored element with an integer-castable id is refused' => [
            'StoredContentElement',
            ['id' => '12', 'component' => 'core:text'],
            false,
        ];

        yield 'a stored element carrying the reserved root literal is refused' => [
            'StoredContentElement',
            ['id' => '__page_context_root__', 'component' => 'core:text'],
            false,
        ];

        yield 'a move request naming an author-supplied element is accepted' => [
            'ContentLayoutMoveElementRequest',
            ['elementId' => 'el-1'],
            true,
        ];

        yield 'a move request naming an integer-castable element is refused' => [
            'ContentLayoutMoveElementRequest',
            ['elementId' => '12'],
            false,
        ];

        yield 'a move request to the root, with newParentId null, is accepted' => [
            'ContentLayoutMoveElementRequest',
            ['elementId' => 'el-1', 'newParentId' => null],
            true,
        ];

        yield 'a move request to an author-supplied parent is accepted' => [
            'ContentLayoutMoveElementRequest',
            ['elementId' => 'el-1', 'newParentId' => 'el-2', 'newSlot' => 'main'],
            true,
        ];

        yield 'a move request to an integer-castable parent is refused' => [
            'ContentLayoutMoveElementRequest',
            ['elementId' => 'el-1', 'newParentId' => '12', 'newSlot' => 'main'],
            false,
        ];

        yield 'a wrap request whose element id list holds an integer-castable id is refused' => [
            'ContentLayoutWrapElementsRequest',
            ['elementIds' => ['el-1', '12'], 'containerType' => 'core:section'],
            false,
        ];
    }

    private function validator(): Validator
    {
        $document = $this->getContainer()->get(OpenApi3Generator::class)->generate(
            $this->getContainer()->get(DefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::API
        );

        $validator = new Validator();
        $validator->resolver()?->registerRaw(self::asJsonValue($document), self::DOCUMENT_URI);

        return $validator;
    }

    /**
     * opis judges the JSON value, so an associative array has to become the object a request body decodes to.
     *
     * @param array<array-key, mixed> $value
     */
    private static function asJsonValue(array $value): mixed
    {
        return json_decode(json_encode($value, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);
    }

    private static function explain(ValidationResult $result): string
    {
        $error = $result->error();

        if ($error === null) {
            return 'the document accepted the payload';
        }

        return json_encode((new ErrorFormatter())->format($error), \JSON_THROW_ON_ERROR) ?: '';
    }
}
