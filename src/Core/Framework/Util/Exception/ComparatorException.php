<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Exception;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use UtilException::operatorNotSupported()
 */
#[Package('framework')]
class ComparatorException extends HttpException
{
    public const OPERATOR_NOT_SUPPORTED = 'CONTENT__OPERATOR_NOT_SUPPORTED';

    public static function operatorNotSupported(string $operator): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OPERATOR_NOT_SUPPORTED,
            'Operator "{{ operator }}" is not supported.',
            ['operator' => $operator]
        );
    }
}
