<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class UserExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if ($e instanceof UniqueConstraintViolationException && preg_match('/for key \'(?:user\.)?uniq\.user\.username\'/', $e->getMessage())) {
            return UserException::usernameNotUnique($e);
        }

        return null;
    }
}
