<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('data-services')]
class ConsentException extends HttpException
{
    final public const NOT_FOUND = 'SYSTEM__CONSENT_NOT_FOUND';

    public static function notFound(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NOT_FOUND,
            'Consent with name "{{ name }}" not found.',
            ['name' => $name]
        );
    }
}