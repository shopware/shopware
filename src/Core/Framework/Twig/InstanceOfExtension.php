<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

class InstanceOfExtension extends \Twig_Extension
{
    public function getTests(): array
    {
        return [
            'instanceof' => new \Twig_SimpleTest('instanceof', [
                $this, 'isInstanceOf',
            ]),
        ];
    }

    public function isInstanceOf($var, $class): bool
    {
        return (new \ReflectionClass($class))->isInstance($var);
    }
}
