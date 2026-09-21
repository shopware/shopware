<?php declare(strict_types=1);

use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;

/**
 * Runs while Composer initializes, before PHPUnit can start collecting coverage.
 */
/**
 * @codeCoverageIgnoreStart
 */
/**
 * @param non-empty-string $previousClassName
 * @param class-string $currentClassName
 */
$registerAlias = static function (string $previousClassName, string $currentClassName): void {
    if (class_exists($previousClassName, autoload: false)) {
        $registeredClassName = (new ReflectionClass($previousClassName))->getName();

        if ($registeredClassName !== $currentClassName) {
            throw new LogicException(\sprintf('Cannot register class alias "%s" to "%s": the name already refers to "%s".', $previousClassName, $currentClassName, $registeredClassName));
        }

        return;
    }

    class_alias($currentClassName, $previousClassName);
};

foreach (ClassAliasRegistry::ALIASES as $previousClassName => $currentClassName) {
    $registerAlias($previousClassName, $currentClassName);
}
/**
 * @codeCoverageIgnoreEnd
 */
