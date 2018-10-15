<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write;

use Shopware\Core\Framework\ShopwareException;
use Throwable;

class InsufficientDeletePermissionException extends \DomainException implements ShopwareException
{
    private const CONCERN = 'insufficient-permission';

    public function __construct(string $missingPermission, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Cannot delete entity. Missing permission: %s', $missingPermission),
            $code,
            $previous
        );
    }

    public function getConcern(): string
    {
        return self::CONCERN;
    }
}
