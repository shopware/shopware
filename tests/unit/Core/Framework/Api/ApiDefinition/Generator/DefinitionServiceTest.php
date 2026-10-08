<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DefinitionService::class)]
class DefinitionServiceTest extends TestCase
{
    public function testGenerateThrowsWhenFormatIsUnsupported(): void
    {
        $definitionService = $this->createDefinitionService();

        $this->expectExceptionObject(ApiException::apiDefinitionGeneratorNotFound('unsupported-format'));

        $definitionService->generate('unsupported-format');
    }

    public function testGenerateThrowsWhenApiTypeIsUnsupported(): void
    {
        $generator = static::createStub(ApiDefinitionGeneratorInterface::class);
        $generator->method('supports')->willReturn(true);

        $definitionService = $this->createDefinitionService($generator);

        $this->expectExceptionObject(ApiException::apiDefinitionGeneratorNotFound('unsupported-api'));

        /** @phpstan-ignore argument.type (Intentionally passing an unsupported type for testing purpose) */
        $definitionService->generate(type: 'unsupported-api');
    }

    public function testConversionFromStringToApiType(): void
    {
        $definitionService = $this->createDefinitionService();

        static::assertNull($definitionService->toApiType('foobar'));
        static::assertSame(DefinitionService::TYPE_JSON_API, $definitionService->toApiType('jsonapi'));
        static::assertSame(DefinitionService::TYPE_JSON, $definitionService->toApiType('json'));
    }

    private function createDefinitionService(ApiDefinitionGeneratorInterface ...$generators): DefinitionService
    {
        return new DefinitionService(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            ...$generators
        );
    }
}
