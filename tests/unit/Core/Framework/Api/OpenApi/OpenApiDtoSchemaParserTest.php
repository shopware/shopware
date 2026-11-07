<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Shopware\Core\Framework\Api\OpenApi\OpenApiOperationReader;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiDtoSchemaParser::class)]
class OpenApiDtoSchemaParserTest extends TestCase
{
    private OpenApiDtoSchemaParser $parser;

    protected function setUp(): void
    {
        $this->parser = new OpenApiDtoSchemaParser(new OpenApiOperationReader());
    }

    public function testCyclicRequestBodyReferenceThrowsException(): void
    {
        $schema = $this->loadSchema('invalidSchemas/cyclicRequestBody.json');
        $this->expectExceptionObject(FrameworkException::invalidArgumentException('Cannot resolve reference "#/components/requestBodies/InputAlias".'));

        $this->parser->parse($schema);
    }

    public function testCyclicMediaTypeReferenceThrowsException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException('Cannot resolve reference "#/components/mediaTypes/PayloadAlias".'));

        $this->parser->parse($this->loadSchema('invalidSchemas/cyclicMediaType.json'));
    }

    public function testMissingMediaTypeReferenceThrowsException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException('Cannot resolve reference "#/components/mediaTypes/Missing".'));

        $this->parser->parse($this->loadSchema('invalidSchemas/missingMediaType.json'));
    }

    public function testExternalMediaTypeReferenceThrowsException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException('Cannot resolve reference "payload.json#/components/mediaTypes/Payload".'));

        $this->parser->parse($this->loadSchema('invalidSchemas/externalMediaType.json'));
    }

    public function testCyclicResponseReferenceThrowsException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException('Cannot resolve reference "#/components/responses/PayloadAlias".'));

        $this->parser->parse($this->loadSchema('invalidSchemas/cyclicResponse.json'));
    }

    public function testDuplicateNormalizedPropertiesThrowException(): void
    {
        $this->expectException(FrameworkException::class);

        $this->parser->parse($this->loadSchema('invalidSchemas/duplicateProperties.json'));
    }

    public function testDuplicateDtoNamesThrowException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException(
            'Component schema and operation generate the same DTO name "CreateOrderRequest". Use unique names or mark the composition-only component with "x-dto-inline": true.',
        ));

        $this->parser->parse($this->loadSchema('invalidSchemas/duplicateDtoNames.json'));
    }

    public function testConflictingInheritedComponentPackagesThrowException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException(
            'Component schema "Order" inherits conflicting DTO packages "checkout" and "framework". Set "x-dto-package" explicitly on the component.',
        ));

        $this->parser->parse(
            $this->loadSchema('invalidSchemas/conflictingInheritedComponentPackages.json'),
            includeComponentSchemas: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSchema(string $fixture): array
    {
        $schema = json_decode((new Filesystem())->readFile(__DIR__ . '/_fixtures/' . $fixture), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($schema);

        return $schema;
    }
}
