<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGeneratedFile;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationCheckResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoProperty;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;

/**
 * @internal
 */
#[CoversClass(OpenApiDtoGenerator::class)]
#[CoversClass(OpenApiDtoSchemaParser::class)]
#[CoversClass(OpenApiDtoClassRenderer::class)]
#[CoversClass(OpenApiDtoDefinition::class)]
#[CoversClass(OpenApiDtoProperty::class)]
#[CoversClass(OpenApiDtoGeneratedFile::class)]
#[CoversClass(OpenApiDtoGenerationResult::class)]
#[CoversClass(OpenApiDtoGenerationCheckResult::class)]
class OpenApiDtoGeneratorTest extends TestCase
{
}
