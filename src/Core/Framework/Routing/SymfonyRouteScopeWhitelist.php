<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

/**
 * @package core
 */
class SymfonyRouteScopeWhitelist implements RouteScopeWhitelistInterface
{
    /**
     * {@inheritdoc}
     */
    public function applies(string $controllerClass): bool
    {
        return strncmp($controllerClass, 'Symfony\\', 8) === 0;
    }
}
