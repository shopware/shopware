<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class SalesChannelException extends HttpException
{
    final public const SALES_CHANNEL_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__SALES_CHANNEL_DOES_NOT_EXISTS';

    final public const LANGUAGE_INVALID_EXCEPTION = 'SYSTEM__LANGUAGE_INVALID_EXCEPTION';

    final public const COUNTRY_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__COUNTRY_DOES_NOT_EXISTS_EXCEPTION';

    final public const CURRENCY_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__CURRENCY_DOES_NOT_EXISTS_EXCEPTION';

    final public const COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION';

    final public const SALES_CHANNEL_NOT_AVAILABLE_EXCEPTION = 'SYSTEM__SALES_CHANNEL_NOT_AVAILABLE_EXCEPTION';

    final public const NO_CONTEXT_DATA_EXCEPTION = 'SYSTEM__NO_CONTEXT_DATA_EXCEPTION';

    public static function salesChannelNotFound(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SALES_CHANNEL_DOES_NOT_EXISTS_EXCEPTION,
            'Sales channel with id "{{ salesChannelId }}" not found or not valid!.',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public static function currencyNotFound(string $currencyId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CURRENCY_DOES_NOT_EXISTS_EXCEPTION,
            'Currency with id "{{ currencyId }}" not found!.',
            ['currencyId' => $currencyId]
        );
    }

    public static function countryStateNotFound(string $countryStateId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION,
            'Country state with id "{{ countryStateId }}" not found!.',
            ['countryStateId' => $countryStateId]
        );
    }

    public static function countryNotFound(string $countryId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::COUNTRY_DOES_NOT_EXISTS_EXCEPTION,
            'Country with id "{{ countryId }}" not found!.',
            ['countryId' => $countryId]
        );
    }

    public static function noContextData(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::NO_CONTEXT_DATA_EXCEPTION,
            'No context data found for SalesChannel "{{ salesChannelId }}"',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public static function invalidLanguageId(): ShopwareHttpException
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_INVALID_EXCEPTION,
            'Provided languageId is not a valid uuid',
        );
    }

    public static function languageNotFound(string $languageId): ShopwareHttpException
    {
        return new LanguageNotFoundException($languageId);
    }

    /**
     * @param array<string> $availableLanguages
     */
    public static function providedLanguageNotAvailable(string $languageId, array $availableLanguages): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::SALES_CHANNEL_NOT_AVAILABLE_EXCEPTION,
            sprintf('Provided language "%s" is not in list of available languages: %s', $languageId, implode(', ', $availableLanguages)),
        );
    }

    public static function unknownPaymentMethod(string $paymentMethodId): ShopwareHttpException
    {
        return new UnknownPaymentMethodException($paymentMethodId);
    }
}
