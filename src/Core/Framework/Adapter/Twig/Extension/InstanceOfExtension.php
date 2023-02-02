<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

#[Package('core')]
class InstanceOfExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            'instanceof' => new TwigTest('instanceof', $this->isInstanceOf(...)),
        ];
    }

    public function isInstanceOf($var, $class): bool
    {
        return (new \ReflectionClass($class))->isInstance($var);
    }
}
