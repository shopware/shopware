<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ElementIdRule;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;

/**
 * The element-id value domain is stated twice: {@see ElementIdRule} states it for the two PHP sites that
 * enforce it, and the published OpenAPI schemas promise it to clients. Only the second is inert — nothing
 * executes a schema — so a pattern narrower than the rule locks a schema-validating client out of ids the
 * server accepts, with no test going red. That is how `^[0-9a-f]{32}$` came to sit on 24 element-id fields
 * while decode admitted `el-1`. One table of ids, each put through the pattern as published and through
 * decode, pins the agreement rather than the instances of it found so far.
 *
 * The pattern is read out of the schema, never restated here: a test carrying its own copy stays green
 * through exactly the drift it exists to catch.
 *
 * The two agree on every input but the empty string, which {@see divergentIdProvider} carries with its
 * reason. That one diverges in the safe direction — a client withholds a write the server would have taken,
 * rather than sending one the server strands — and the write descriptor refuses it anyway.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElementCodec::class)]
class ElementIdSchemaConformanceTest extends StoredElementCodecTestCase
{
    private const COMPONENT_SCHEMA = 'src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/components/schemas/ContentElementId.json';

    private const STORE_API_PATHS = 'src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/paths/content.json';

    #[DataProvider('admittedIdProvider')]
    #[TestDox('admits $_dataName on both sides')]
    public function testThePublishedPatternAdmitsWhatDecodeAdmits(string $id): void
    {
        static::assertSame(
            1,
            preg_match(self::publishedPattern(), $id),
            \sprintf('the published pattern refuses the id "%s", which decode admits', $id)
        );

        static::assertSame($id, $this->codec()->decode(self::baseWire(['id' => $id]))->id);
    }

    #[DataProvider('refusedIdProvider')]
    #[TestDox('refuses $_dataName on both sides')]
    public function testThePublishedPatternRefusesWhatDecodeRefuses(string $id, ContentSystemException $expected): void
    {
        static::assertSame(
            0,
            preg_match(self::publishedPattern(), $id),
            \sprintf('the published pattern admits the id "%s", which decode refuses', $id)
        );

        $this->expectExceptionObject($expected);

        $this->codec()->decode(self::baseWire(['id' => $id]));
    }

    #[DataProvider('divergentIdProvider')]
    #[TestDox('refuses $_dataName, which decode alone admits')]
    public function testThePublishedPatternIsStricterThanDecodeOnlyWhereRecorded(string $id): void
    {
        static::assertSame(0, preg_match(self::publishedPattern(), $id));

        static::assertSame($id, $this->codec()->decode(self::baseWire(['id' => $id]))->id);
    }

    /**
     * The Store API is a separate document, so its `elementId` query parameter cannot reference the Admin API
     * component and carries the pattern inline. Nothing but this assertion ties the two copies together.
     */
    #[TestDox('the Store API elementId query parameter carries the same pattern')]
    public function testTheStoreApiQueryParameterCarriesThePublishedPattern(): void
    {
        $patterns = [];

        foreach (self::readSchema(self::STORE_API_PATHS)['paths'] as $operations) {
            foreach ($operations as $operation) {
                foreach ($operation['parameters'] ?? [] as $parameter) {
                    if (($parameter['name'] ?? null) !== 'elementId' || ($parameter['in'] ?? null) !== 'query') {
                        continue;
                    }

                    $patterns[] = $parameter['schema']['pattern'] ?? '<no pattern declared>';
                }
            }
        }

        static::assertNotSame([], $patterns, 'the Store API declares no elementId query parameter');
        static::assertSame([self::rawPublishedPattern()], array_values(array_unique($patterns)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function admittedIdProvider(): iterable
    {
        yield 'a server-minted hex id' => ['0188fa1b2c3d4e5f6a7b8c9d0e1f2a3b'];

        yield 'an author-supplied id' => ['el-1'];

        yield 'a hyphenated author-supplied id' => ['foo-bar-123'];

        yield 'a leading-zero digit string, which PHP keeps as a string key' => ['012'];

        yield 'a digit string carrying a decimal point' => ['1.5'];

        yield 'a single character' => ['a'];

        yield 'an id carrying a space' => ['a b'];

        yield 'an id carrying a tab, which is no line terminator' => ["hero\tfoot"];

        yield 'an id carrying NEL, a Unicode newline ECMA-262 does not count' => ["hero\u{0085}foot"];

        yield 'an id carrying a vertical tab, likewise' => ["hero\u{000B}foot"];
    }

    /**
     * @return iterable<string, array{string, ContentSystemException}>
     */
    public static function refusedIdProvider(): iterable
    {
        yield 'the reserved virtual-root literal' => [
            VirtualRootWrapper::VIRTUAL_ROOT_ID,
            ContentSystemException::invalidElementId(VirtualRootWrapper::VIRTUAL_ROOT_ID, 'it is the reserved virtual-root id'),
        ];

        yield 'the integer-castable string "0"' => [
            '0',
            ContentSystemException::invalidElementId('0', 'it reads as an integer'),
        ];

        yield 'a positive integer-castable string' => [
            '12',
            ContentSystemException::invalidElementId('12', 'it reads as an integer'),
        ];

        yield 'a negative integer-castable string' => [
            '-3',
            ContentSystemException::invalidElementId('-3', 'it reads as an integer'),
        ];

        yield 'a negative zero, which PHP alone would have kept as a string key' => [
            '-0',
            ContentSystemException::invalidElementId('-0', 'it reads as an integer'),
        ];

        yield 'a digit string past PHP_INT_MAX, likewise' => [
            '9223372036854775808',
            ContentSystemException::invalidElementId('9223372036854775808', 'it reads as an integer'),
        ];

        yield 'an id carrying a line feed' => [
            "hero\nfoot",
            ContentSystemException::invalidElementId("hero\nfoot", 'it contains the line terminator U+000A'),
        ];

        yield 'an id carrying a carriage return' => [
            "hero\rfoot",
            ContentSystemException::invalidElementId("hero\rfoot", 'it contains the line terminator U+000D'),
        ];

        yield 'an id carrying a line separator' => [
            "hero\u{2028}foot",
            ContentSystemException::invalidElementId("hero\u{2028}foot", 'it contains the line terminator U+2028'),
        ];

        yield 'an id carrying a paragraph separator' => [
            "hero\u{2029}foot",
            ContentSystemException::invalidElementId("hero\u{2029}foot", 'it contains the line terminator U+2029'),
        ];
    }

    /**
     * The one input the pattern refuses that decode on its own would take. It is not a divergence at the API
     * boundary: the write descriptor's `NotBlank` refuses it, so the schema agrees with the path a request
     * actually travels. Decode admits it because an already-stored blank id must stay readable.
     *
     * @return iterable<string, array{string}>
     */
    public static function divergentIdProvider(): iterable
    {
        yield 'the empty string, which the write descriptor refuses through NotBlank' => [''];
    }

    /**
     * A JSON Schema `pattern` is ECMA-262, and PCRE is not it. Running the published expression through
     * `preg_match` unchanged is what let `hero\r` read as agreed here while every client refuses it: ECMA's
     * `.` excludes the four {@see ElementIdRule::LINE_TERMINATORS}, PCRE's excludes only `\n`. The two also
     * part over `$`, which PCRE lets match before a trailing newline.
     *
     * Both are closed by translation rather than by a second engine, because the only ECMA engine in the
     * repository is ajv, and reaching it from a PHP unit test costs a Node process per case. The translation
     * is exact only for a restricted alphabet — no backslash escapes, and no `.` inside a character class —
     * so the alphabet is asserted before the substitution rather than assumed. Widen the published pattern
     * beyond it and this assertion fails, which is the intended way to find out.
     */
    private static function publishedPattern(): string
    {
        $pattern = self::rawPublishedPattern();

        static::assertMatchesRegularExpression(
            '/^[A-Za-z0-9_^$()?!|*+.\[\]-]+$/',
            $pattern,
            'the published pattern left the alphabet this ECMA translation is exact for'
        );
        static::assertSame(
            0,
            preg_match('/\[[^\]]*\.[^\]]*\]/', $pattern),
            'the published pattern puts a literal "." inside a character class, which the translation would corrupt'
        );

        return '/' . str_replace('.', '[^\n\r\x{2028}\x{2029}]', $pattern) . '/uD';
    }

    private static function rawPublishedPattern(): string
    {
        $pattern = self::readSchema(self::COMPONENT_SCHEMA)['components']['schemas']['ContentElementId']['pattern'] ?? null;

        static::assertIsString($pattern, 'the ContentElementId component declares no pattern');

        return $pattern;
    }

    /**
     * @return array<string, mixed>
     */
    private static function readSchema(string $relativePath): array
    {
        $contents = file_get_contents(\dirname(__DIR__, 7) . '/' . $relativePath);

        static::assertIsString($contents, \sprintf('cannot read the schema at %s', $relativePath));

        $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($decoded);

        return $decoded;
    }
}
