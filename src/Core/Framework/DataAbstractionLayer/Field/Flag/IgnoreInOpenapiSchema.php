<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Flag to ignore a field via the OpenApiDefinitionSchemaBuilder
 * If this flag is set, make sure you have a custom OpenApiSchema json for that field/entity
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class IgnoreInOpenapiSchema extends Flag
{
    public function parse(): \Generator
    {
        yield 'ignore_in_openapi_schema' => true;
    }
}
