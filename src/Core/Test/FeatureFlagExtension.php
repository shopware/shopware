<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeTestHook;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Test\Annotation\ActiveFeatures;

/**
 * @internal
 *
 * This extension guarantees a clean feature environment for pure unit tests
 */
class FeatureFlagExtension implements BeforeTestHook, AfterTestHook
{
    private AnnotationReader $annotationReader;

    private string $namespacePrefix;

    private ?array $savedFeatureConfig = null;

    private ?array $savedServerVars = null;

    private bool $testMode;

    public function __construct(string $namespacePrefix = 'Shopware\\Tests\\Unit\\', bool $testMode = false)
    {
        $this->annotationReader = new AnnotationReader();
        $this->namespacePrefix = $namespacePrefix;
        $this->testMode = $testMode;
    }

    public function executeBeforeTest(string $test): void
    {
        preg_match('/([^:]+)::([^$ ]+)($| )/', $test, $matches);
        $class = $matches[1];
        $method = $matches[2];

        // do not run when this class is unit tested
        if (!$this->testMode && $class === 'Shopware\Tests\Unit\Core\Test\FeatureFlagExtensionTest') {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $reflectedMethod = new \ReflectionMethod($class, $method);

        $features = $this->annotationReader->getMethodAnnotation($reflectedMethod, ActiveFeatures::class) ?? [];

        $this->savedFeatureConfig = null;

        if (!$features && !str_starts_with($class, $this->namespacePrefix)) {
            return;
        }

        $this->savedFeatureConfig = Feature::getRegisteredFeatures();
        $this->savedServerVars = $_SERVER;

        Feature::resetRegisteredFeatures();
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'v6.') || $key === 'PERFORMANCE_TWEAKS' || str_starts_with($key, 'FEATURE_')) {
                // set to false so that $_ENV is not checked
                $_SERVER[$key] = false;
            }
        }

        if ($features) {
            foreach ($features->features as $feature) {
                $_SERVER[Feature::normalizeName($feature)] = true;
            }
        }
    }

    public function executeAfterTest(string $test, float $time): void
    {
        preg_match('/([^:]+)::([^$ ]+)($| )/', $test, $matches);
        $class = $matches[1];

        // do not run when this class is unit tested
        if (!$this->testMode && $class === 'Shopware\Tests\Unit\Core\Test\FeatureFlagExtensionTest') {
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
