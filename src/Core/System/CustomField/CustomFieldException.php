<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
class CustomFieldException extends HttpException
{
    public const CUSTOM_FIELD_NAME_INVALID = 'CUSTOM_FIELD_NAME_INVALID';

    public static function customFieldNameInvalid(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOM_FIELD_NAME_INVALID,
            'Invalid custom field name: It must begin with a letter or underscore, followed by letters, numbers, or underscores.',
            ['field' => 'name', 'value' => $name]
        );
    }
}
