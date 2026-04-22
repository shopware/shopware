<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber;

use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;
use PHPUnit\Event\TestSuite\TestSuiteForTestClass;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\FeatureFlagExtension;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\SavedConfig;

/**
 * Mirrors {@see TestPreparationStartedSubscriber} at the class level so feature flags are already
 * active when `setUpBeforeClass` runs, not only by the time `setUp` is called.
 *
 * @internal
 */
#[Package('framework')]
class TestSuiteStartedSubscriber implements StartedSubscriber
{
    public function __construct(private readonly SavedConfig $savedConfig)
    {
    }

    public function notify(Started $event): void
    {
        $testSuite = $event->testSuite();

        if (!$testSuite instanceof TestSuiteForTestClass) {
            return;
        }

        $className = $testSuite->className();

        if (!$this->namespaceIsAllowed($className)) {
            return;
        }

        $this->savedConfig->classSavedServerVars = $_SERVER;

        Feature::disableAllInEnv();

        $disabledFlags = $this->collectClassDisabledFlags($className);

        foreach (Feature::getRegisteredFeatures() as $flag => $config) {
            $flag = Feature::normalizeName($flag);
            $_SERVER[$flag] = !\array_key_exists($flag, $disabledFlags);
        }
    }

    /**
     * @param class-string $className
     *
     * @return array<string, true>
     */
    private function collectClassDisabledFlags(string $className): array
    {
        $disabledFlags = [];

        foreach ((new \ReflectionClass($className))->getAttributes(DisabledFeatures::class) as $attribute) {
            /** @var DisabledFeatures $attr */
            $attr = $attribute->newInstance();

            foreach ($attr->features as $featureName) {
                $disabledFlags[Feature::normalizeName($featureName)] = true;
            }
        }

        return $disabledFlags;
    }

    private function namespaceIsAllowed(string $className): bool
    {
        foreach (FeatureFlagExtension::getTestNamespaces() as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
