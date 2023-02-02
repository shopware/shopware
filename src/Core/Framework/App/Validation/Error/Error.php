<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

/**
 * @internal only for use by the app-system
 */
abstract class Error extends \Exception
{
    abstract public function getMessageKey(): string;
}
