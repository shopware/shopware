<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\ForeignKeyConstraintViolationExceptionHandlerTest
 */
#[Package('framework')]
class InvalidForeignKeyReferenceException extends WriteConstraintViolationException
{
}
