<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;

/**
 * The element-id value domain is stated three times: {@see StoredElementCodec} admits it on decode,
 * {@see StoredTreeConstraints} refuses it on write, and the published OpenAPI schemas promise it to clients.
 * Only the third is inert — nothing executes a schema — so a pattern narrower than decode locks a
 * schema-validating client out of ids the server accepts, with no test going red. That is how
 * `^[0-9a-f]{32}$` came to sit on 24 element-id fields while decode admitted `el-1`. One table of ids, each
 * put through the pattern as published and through decode, pins the agreement rather than the instances of it
 * found so far.
 *
 * The pattern is read out of the schema, never restated here: a test carrying its own copy stays green
 * through exactly the drift it exists to catch.
 *
 * Where the two do not agree, they diverge in one direction only, and {@see divergentIdProvider} names every
 * input and its reason. That direction is the safe one — a client withholds a write the server would have
 * taken, rather than sending one the server strands.
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
            ContentSystemException::invalidElementId('0', 'PHP casts it to an integer array key'),
        ];

        yield 'a positive integer-castable string' => [
            '12',
            ContentSystemException::invalidElementId('12', 'PHP casts it to an integer array key'),
        ];

        yield 'a negative integer-castable string' => [
            '-3',
            ContentSystemException::invalidElementId('-3', 'PHP casts it to an integer array key'),
        ];
    }

    /**
     * Every input the pattern refuses that decode on its own would take, each with the reason it stays that
     * way. The empty string is not a real divergence at the API boundary: the write descriptor's `NotBlank`
     * refuses it, so the schema agrees with the path a request actually travels. The other two cannot be
     * closed, because a regular expression cannot express PHP's platform-dependent integer bound.
     *
     * @return iterable<string, array{string}>
     */
    public static function divergentIdProvider(): iterable
    {
        yield 'the empty string, which the write descriptor refuses through NotBlank' => [''];

        yield 'a negative zero, which PHP does not cast to an integer key' => ['-0'];

        yield 'a digit string past PHP_INT_MAX, which PHP therefore keeps as a string key' => ['9223372036854775808'];
    }

    /**
     * `D` anchors `$` at the absolute end of the subject, matching what a JSON Schema consumer's ECMA regex
     * does with the same expression.
     */
    private static function publishedPattern(): string
    {
        return '/' . self::rawPublishedPattern() . '/D';
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
