<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use Shopware\Core\Framework\Log\Package;
use OpenApi\Analysis;

/**
 * @package core
 */
#[Package('core')]
class DeactivateValidationAnalysis extends Analysis
{
    public function validate(): bool
    {
        return false;
        //deactivate Validitation
    }
}
