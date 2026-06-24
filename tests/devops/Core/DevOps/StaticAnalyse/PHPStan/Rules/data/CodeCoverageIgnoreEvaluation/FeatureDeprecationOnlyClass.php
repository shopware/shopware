<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Shopware\Core\Framework\Feature;

/**
 * @codeCoverageIgnore
 */
class FeatureDeprecationOnlyClass
{
    public string $label = '';

    public function setLabel(string $label): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'setLabel is deprecated');
        $this->label = $label;
    }
}
