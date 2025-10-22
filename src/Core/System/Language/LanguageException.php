<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class LanguageException extends HttpException
{
    public const VALUE_NOT_SUPPORTED = 'LANGUAGE__RULE_VALUE_NOT_SUPPORTED';

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function unsupportedValue(string $type, string $class): self|UnsupportedValueException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new UnsupportedValueException($type, $class);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VALUE_NOT_SUPPORTED,
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class]
        );
    }
}
