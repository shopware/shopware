<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

class InstanceOfExtension extends \Twig_Extension
{
    public function getTests()
    {
        return [
            'instanceof' => new \Twig_SimpleTest('instanceof', [
                $this, 'isInstanceOf',
            ]),
        ];
    }

    public function isInstanceOf($var, $class): bool
    {
        $reflectionClass = new \ReflectionClass($class);

        return $reflectionClass->isInstance($var);
    }
}
