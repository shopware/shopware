<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SalesChannelException::class)]
class SalesChannelExceptionTest extends TestCase
{
    #[DataProvider('exceptionDataProvider')]
    public function testExceptions(ShopwareHttpException|SalesChannelException $exception, int $statusCode, string $errorCode, string $message): void
    {
        static::assertSame($statusCode, $exception->getStatusCode());
        static::assertSame($errorCode, $exception->getErrorCode());
        static::assertSame($message, $exception->getMessage());
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
            'message' => 'Could not find country with id "myCustomCountry"',
        ];

        yield SalesChannelException::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => SalesChannelException::countryStateNotFound('myCustomCountryState'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => SalesChannelException::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Could not find country state with id "myCustomCountryState"',
        ];

        yield SalesChannelException::CURRENCY_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => SalesChannelException::currencyNotFound('myCustomCurrency'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => SalesChannelException::CURRENCY_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Could not find currency with id "myCustomCurrency"',
        ];

        yield SalesChannelException::LANGUAGE_NOT_FOUND => [
            'exception' => SalesChannelException::languageNotFound('myCustomLanguage'),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => SalesChannelException::LANGUAGE_NOT_FOUND,
            'message' => 'Could not find language with id "myCustomLanguage"',
        ];

        yield 'payment method not found exception' => [
            'exception' => SalesChannelException::unknownPaymentMethod('myCustomPaymentMethod'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CHECKOUT__UNKNOWN_PAYMENT_METHOD',
            'message' => 'Could not find payment method with id "myCustomPaymentMethod"',
        ];

        yield SalesChannelException::SALES_CHANNEL_DOMAIN_IN_USE => [
            'exception' => SalesChannelException::salesChannelDomainInUse(),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => SalesChannelException::SALES_CHANNEL_DOMAIN_IN_USE,
            'message' => 'The sales channel domain cannot be deleted because it is still referenced in product exports.',
        ];
    }
}
