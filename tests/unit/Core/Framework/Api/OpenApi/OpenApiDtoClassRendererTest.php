<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiDtoClassRenderer::class)]
class OpenApiDtoClassRendererTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEnumSchemas(): iterable
    {
        yield 'enum cases normalize to the same name' => ['enumCaseCollision.json'];
        yield 'enum value cannot produce a case name' => ['emptyEnumCase.json'];
    }

    #[DataProvider('invalidEnumSchemas')]
    public function testInvalidEnumCannotBeRendered(string $fixture): void
    {
        $schema = json_decode((new Filesystem())->readFile(__DIR__ . '/_fixtures/invalidSchemas/' . $fixture), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($schema);
        $definitions = (new OpenApiDtoSchemaParser())->parse($schema);
        $renderer = new OpenApiDtoClassRenderer(new MockClock('2026-07-07 00:00:00'));

        $this->expectException(FrameworkException::class);

        foreach ($definitions as $definition) {
            $renderer->renderClass($definition, 'App\\DTO');
        }
    }
}
