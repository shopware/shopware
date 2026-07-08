<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnmappedFieldException as DalUnmappedFieldException;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use {@see DalUnmappedFieldException} instead
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class UnmappedFieldException extends DalUnmappedFieldException
{
}
