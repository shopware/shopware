<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ClassAliasMap
{
    /**
     * @var array<lowercase-string, class-string>|null
     */
    private ?array $classAliases = null;

    /**
     * @return class-string|null
     */
    public function canonicalClassName(string $className): ?string
    {
        return $this->classAliases()[\strtolower($className)] ?? null;
    }

    /**
     * @return array<lowercase-string, class-string>
     */
    private function classAliases(): array
    {
        if ($this->classAliases !== null) {
            return $this->classAliases;
        }

        $this->classAliases = [];
        foreach (\get_declared_classes() as $declaredClassName) {
            if (!\str_starts_with(\strtolower($declaredClassName), 'shopware\\')) {
                continue;
            }

            $reflection = new \ReflectionClass($declaredClassName);
            $resolvedClassName = $reflection->getName();
            if (\strcasecmp($declaredClassName, $resolvedClassName) === 0) {
                continue;
            }

            foreach ($reflection->getAttributes(ClassMoved::class) as $attribute) {
                if (\strcasecmp($attribute->newInstance()->previousClassName, $declaredClassName) === 0) {
                    $this->classAliases[\strtolower($declaredClassName)] = $resolvedClassName;
                }
            }
        }

        return $this->classAliases;
    }
}
