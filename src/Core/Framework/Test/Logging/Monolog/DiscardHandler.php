<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Logging\Monolog;

use Monolog\Handler\AbstractHandler;

/**
 * This handler only exists, because Monolog NullHandlers cannot be specified in xml configuration files
 *
 * @see https://github.com/symfony/monolog-bundle/issues/133
 */
class DiscardHandler extends AbstractHandler
{
    public function handle(array $record): bool
    {
        return true;
    }

    public function isHandling(array $record): bool
    {
        return true;
    }
}
