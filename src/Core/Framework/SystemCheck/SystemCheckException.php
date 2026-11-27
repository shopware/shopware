<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class SystemCheckException extends HttpException
{
    public const UNKNOWN_CHECK = 'FRAMEWORK__SYSTEM_CHECK_UNKNOWN';

    public static function unknownCheck(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_CHECK,
            'There is no system check named "{{ name }}".',
            ['name' => $name],
        );
    }
}
