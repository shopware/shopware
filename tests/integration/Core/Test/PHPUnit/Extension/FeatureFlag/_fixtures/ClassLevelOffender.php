<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Test\PHPUnit\Extension\FeatureFlag\_fixtures;

use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal Not a test — reflection fixture for TestPreparationStartedSubscriberTest. It must live in the
 * integration namespace because the subscriber's rejection is keyed on it.
 */
#[DisabledFeatures(['v6.8.0.0'])]
class ClassLevelOffender
{
    public function testSomething(): void
    {
    }
}
