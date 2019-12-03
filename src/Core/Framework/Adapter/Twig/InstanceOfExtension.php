<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

class InstanceOfExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            'instanceof' => new TwigTest('instanceof', [
                $this, 'isInstanceOf',
            ]),
        ];
    }

    public function isInstanceOf($var, $class): bool
    {
        return (new \ReflectionClass($class))->isInstance($var);
    }
}
