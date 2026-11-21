<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Analysis;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement, the OpenAPI generators no longer use swagger-php
 */
#[Package('framework')]
class DeactivateValidationAnalysis extends Analysis
{
    public function validate(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        return false;
    }
}
