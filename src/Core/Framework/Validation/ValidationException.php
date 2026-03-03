<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ValidationException extends HttpException
{
    public const MISSING_REQUEST_PARAMETER = 'VALIDATION__REQUEST_PARAMETER_MISSING';

    public static function missingRequestParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name]
        );
    }
}