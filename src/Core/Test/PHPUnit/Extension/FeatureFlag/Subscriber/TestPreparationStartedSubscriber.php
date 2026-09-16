<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber;

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\FeatureFlagExtension;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\SavedConfig;

/**
 * @internal
 */
#[Package('framework')]
class TestPreparationStartedSubscriber implements PreparationStartedSubscriber
{
    private const INTEGRATION_NAMESPACE_PREFIX = 'Shopware\\Tests\\Integration\\';

    public function __construct(private readonly SavedConfig $savedConfig)
    {
    }

    public function notify(PreparationStarted $event): void
    {
        $test = $event->test();

        if (!$test->isTestMethod()) {
            return;
        }

        $class = $test->className();
        $method = $test->methodName();

        if (!$this->namespaceIsAllowed($class)) {
            $this->rejectDisabledFeatures($class, $method);

            return;
        }

        $reflectedMethod = new \ReflectionMethod($class, $method);

        /** @var list<\ReflectionAttribute<DisabledFeatures>> $disabledFeatures */
        $disabledFeatures = array_merge(
            $reflectedMethod->getAttributes(DisabledFeatures::class),
            $reflectedMethod->getDeclaringClass()->getAttributes(DisabledFeatures::class),
        );

        $this->savedConfig->savedFeatureConfig = Feature::getRegisteredFeatures();
        $this->savedConfig->savedServerVars = $_SERVER;

        Feature::resetRegisteredFeatures();
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'v6.') || str_starts_with($key, 'FEATURE_') || str_starts_with($key, 'V6_')) {
                // set to false so that $_ENV is not checked
                $_SERVER[$key] = false;
            }
        }

        $disabledFlags = [];
        foreach ($disabledFeatures as $disabledFeature) {
            /** @var DisabledFeatures $attr */
            $attr = $disabledFeature->newInstance();

            foreach ($attr->features as $featureName) {
                $disabledFlags[Feature::normalizeName($featureName)] = true;
            }
        }

        foreach ($this->savedConfig->savedFeatureConfig as $flag => $config) {
            $flag = Feature::normalizeName($flag);
            $_SERVER[$flag] = !\array_key_exists($flag, $disabledFlags);
        }
    }

    /**
     * The extension only rewrites feature flags for the namespaces in getTestNamespaces(). Everywhere
     * else the attribute is inert, which silently lies in the integration suite: there the feature state
     * comes from the job configuration (integration.yml runs without flags, integration-major.yml with
     * FEATURE_ALL=major), and a test carrying the attribute still runs with the flag active in the major
     * job. Reject the attribute loudly instead: PHPUnit reports the exception as a test-runner warning
     * naming the test and fails the run (exit code 1, verified on PHPUnit 11 and 12). Scoped to this
     * repository's integration namespace so plugin suites (which can opt in via addTestNamespace())
     * keep their current behavior.
     *
     * @param class-string $class
     */
    private function rejectDisabledFeatures(string $class, string $method): void
    {
        if (!str_starts_with($class, self::INTEGRATION_NAMESPACE_PREFIX)) {
            return;
        }

        $reflectedMethod = new \ReflectionMethod($class, $method);

        if ($reflectedMethod->getAttributes(DisabledFeatures::class) === []
            && $reflectedMethod->getDeclaringClass()->getAttributes(DisabledFeatures::class) === []) {
            return;
        }

        throw new \RuntimeException(\sprintf(
            '#[DisabledFeatures] on %s::%s has no effect in the integration suite. Feature state there comes from the job configuration: the default integration job runs with feature flags off, integration-major runs with FEATURE_ALL=major. Remove the attribute; if the test must not run under an active major flag, guard it with Feature::skipTestIfActive() instead.',
            $class,
            $method
        ));
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
