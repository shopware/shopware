<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\SalesChannel\SalesChannelException
 */
class SalesChannelExceptionTest extends TestCase
{
    /**
     * @dataProvider exceptionDataProvider
     */
    public function testExceptions(ShopwareHttpException|SalesChannelException $exception, int $statusCode, string $errorCode, string $message): void
    {
        static::assertEquals($statusCode, $exception->getStatusCode());
        static::assertEquals($errorCode, $exception->getErrorCode());
        static::assertEquals($message, $exception->getMessage());
    }

    /**
     * @return array<string, array{exception: ShopwareHttpException|SalesChannelException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield SalesChannelException::SALES_CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION => [
            'exception' => SalesChannelException::providedLanguageNotAvailable('myCustomScn', ['scn1', 'scn2']),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => SalesChannelException::SALES_CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION,
            'message' => 'Provided language "myCustomScn" is not in list of available languages: scn1, scn2',
        ];

        yield SalesChannelException::SALES_CHANNEL_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => SalesChannelException::salesChannelNotFound('myCustomScn'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => SalesChannelException::SALES_CHANNEL_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Sales channel with id "myCustomScn" not found or not valid!.',
        ];

        yield SalesChannelException::LANGUAGE_INVALID_EXCEPTION => [
            'exception' => SalesChannelException::invalidLanguageId(),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => SalesChannelException::LANGUAGE_INVALID_EXCEPTION,
            'message' => 'Provided languageId is not a valid uuid',
        ];

        yield SalesChannelException::NO_CONTEXT_DATA_EXCEPTION => [
            'exception' => SalesChannelException::noContextData('myCustomScn'),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => SalesChannelException::NO_CONTEXT_DATA_EXCEPTION,
            'message' => 'No context data found for SalesChannel "myCustomScn"',
        ];

        yield SalesChannelException::COUNTRY_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => SalesChannelException::countryNotFound('myCustomCountry'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => SalesChannelException::COUNTRY_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Country with id "myCustomCountry" not found!.',
        ];

        yield SalesChannelException::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => SalesChannelException::countryStateNotFound('myCustomCountryState'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => SalesChannelException::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Country state with id "myCustomCountryState" not found!.',
        ];

        yield SalesChannelException::CURRENCY_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => SalesChannelException::currencyNotFound('myCustomCurrency'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => SalesChannelException::CURRENCY_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Currency with id "myCustomCurrency" not found!.',
        ];

        yield SalesChannelException::LANGUAGE_NOT_FOUND => [
            'exception' => SalesChannelException::languageNotFound('myCustomLanguage'),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => SalesChannelException::LANGUAGE_NOT_FOUND,
            'message' => 'The language "myCustomLanguage" was not found.',
        ];

        if (!Feature::isActive('v6.6.0.0')) {
            yield 'payment method not found exception' => [
                'exception' => SalesChannelException::unknownPaymentMethod('myCustomPaymentMethod'),
                'statusCode' => Response::HTTP_NOT_FOUND,
                'errorCode' => 'CHECKOUT__UNKNOWN_PAYMENT_METHOD',
                'message' => 'The payment method myCustomPaymentMethod could not be found.',
            ];
        } else {
            yield 'payment method not found exception' => [
                'exception' => SalesChannelException::unknownPaymentMethod('myCustomPaymentMethod'),
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'errorCode' => 'CHECKOUT__UNKNOWN_PAYMENT_METHOD',
                'message' => 'The payment method myCustomPaymentMethod could not be found.',
            ];
        }
    }
}
