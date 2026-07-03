<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
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
    public function isInstanceOf($var, $class): bool
    {
        return (new \ReflectionClass($class))->isInstance($var);
    }
}
