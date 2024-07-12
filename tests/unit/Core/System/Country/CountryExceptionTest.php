<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CountryException::class)]
class CountryExceptionTest extends TestCase
{
    public function testCountryNotFound(): void
    {
        $exception = CountryException::countryNotFound('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CountryException::COUNTRY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find country with id "id-1"', $exception->getMessage());
        static::assertSame(['entity' => 'country', 'field' => 'id', 'value' => 'id-1'], $exception->getParameters());
    }

    public function testCountryStateNotFound(): void
    {
        $exception = CountryException::countryStateNotFound('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CountryException::COUNTRY_STATE_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find country state with id "id-1"', $exception->getMessage());
        static::assertSame(['entity' => 'country state', 'field' => 'id', 'value' => 'id-1'], $exception->getParameters());
    }
}
