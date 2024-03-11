<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
#[CoversClass(DefinitionService::class)]
class DefinitionServiceTest extends TestCase
{
    public function testConversionFromStringToApiType(): void
    {
        $definitionService = new DefinitionService(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(SalesChannelDefinitionInstanceRegistry::class)
        );

        static::assertNull($definitionService->toApiType('foobar'));
        static::assertSame(DefinitionService::TYPE_JSON_API, $definitionService->toApiType('jsonapi'));
        static::assertSame(DefinitionService::TYPE_JSON, $definitionService->toApiType('json'));
    }
}
