<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class InstanceOfExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            'instanceof' => new TwigTest('instanceof', $this->isInstanceOf(...)),
        ];
    }

    /**
     * The arguments will be natively type-hinted in v6.8.0.
     *
     * @param object $var
     * @param class-string $class
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'var', newType: 'object')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'class', newType: 'class-string')]
    public function isInstanceOf($var, $class): bool
    {
        if (!\is_object($var)) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing a non-object as $var is deprecated.');
        }

        if (!\is_string($class)) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing a non-string as $class is deprecated.');
        }

        return (new \ReflectionClass($class))->isInstance($var);
    }
}
