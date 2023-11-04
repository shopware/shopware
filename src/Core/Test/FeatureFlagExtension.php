<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeTestHook;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Tests\Unit\Core\Test\FeatureFlagExtensionTest;

/**
 * @internal
 * This extension guarantees a clean feature environment for pure unit tests
 */
#[Package('core')]
class FeatureFlagExtension implements BeforeTestHook, AfterTestHook
{
    private readonly AnnotationReader $annotationReader;

    /**
     * @var array<mixed>|null
     */
    private ?array $savedFeatureConfig = null;

    /**
     * @var array<mixed>|null
     */
    private ?array $savedServerVars = null;

    public function __construct(
        private readonly string $namespacePrefix = 'Shopware\\Tests\\Unit\\',
        private readonly bool $testMode = false
    ) {
        $this->annotationReader = new AnnotationReader();
    }

    public function executeBeforeTest(string $test): void
    {
        preg_match('/([^:]+)::([^$ ]+)($| )/', $test, $matches);

        if (empty($matches)) {
            return;
        }

        $class = $matches[1];
        $method = $matches[2];

        // do not run when this class is unit tested
        if (!$this->testMode && $class === FeatureFlagExtensionTest::class) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $reflectedMethod = new \ReflectionMethod($class, $method);

        /** @var DisabledFeatures[] $features */
        $features = array_filter([
            $this->annotationReader->getMethodAnnotation($reflectedMethod, DisabledFeatures::class) ?? [],
            $this->annotationReader->getClassAnnotation($reflectedMethod->getDeclaringClass(), DisabledFeatures::class) ?? [],
        ]);

        $this->savedFeatureConfig = null;

        if (!str_starts_with($class, $this->namespacePrefix)) {
            return;
        }

        $this->savedFeatureConfig = Feature::getRegisteredFeatures();
        $this->savedServerVars = $_SERVER;

        Feature::resetRegisteredFeatures();
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'v6.') || str_starts_with($key, 'FEATURE_') || str_starts_with($key, 'V6_')) {
                // set to false so that $_ENV is not checked
                $_SERVER[$key] = false;
            }
        }

        $disabledFlags = [];
        foreach ($features as $feature) {
            foreach ($feature->features as $featureName) {
                $disabledFlags[Feature::normalizeName($featureName)] = true;
            }
        }

        foreach ($this->savedFeatureConfig as $flag => $config) {
            $flag = Feature::normalizeName($flag);
            $_SERVER[$flag] = !\array_key_exists($flag, $disabledFlags);
        }
    }

    public function executeAfterTest(string $test, float $time): void
    {
        preg_match('/([^:]+)::([^$ ]+)($| )/', $test, $matches);

        if (empty($matches)) {
            return;
        }

        $class = $matches[1];

        // do not run when this class is unit tested
        if (!$this->testMode && $class === FeatureFlagExtensionTest::class) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        if ($this->savedFeatureConfig === null) {
            return;
        }

        $_SERVER = $this->savedServerVars;
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->savedFeatureConfig);
    }
}
