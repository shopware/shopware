<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

/**
 * @internal only for use by the app-system
 * @package core
 */
abstract class Error extends \Exception
{
    abstract public function getMessageKey(): string;
}
