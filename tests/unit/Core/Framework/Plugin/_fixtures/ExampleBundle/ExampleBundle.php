<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle;

use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\FeatureA\FeatureA;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\FeatureB\FeatureB;

/**
 * @internal
 */
class ExampleBundle extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [
            new FeatureA(),
            new FeatureB(),
        ];
    }
}
