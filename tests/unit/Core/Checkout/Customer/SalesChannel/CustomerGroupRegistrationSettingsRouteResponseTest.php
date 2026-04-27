<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[CoversClass(CustomerGroupRegistrationSettingsRouteResponse::class)]
#[Package('checkout')]
class CustomerGroupRegistrationSettingsRouteResponseTest extends TestCase
{
    public function testConstructMapsCompanyRegistrationFlagToRootAndKeepsRegistration(): void
    {
        $registration = new CustomerGroupEntity();
        $registration->setName('Test group');
        $registration->setTranslated([
            'registrationOnlyCompanyRegistration' => true,
            'registrationTitle' => 'Business registration',
        ]);

        $response = new CustomerGroupRegistrationSettingsRouteResponse($registration);
        $object = $response->getObject();

        static::assertSame($registration, $response->getRegistration());
        static::assertInstanceOf(ArrayStruct::class, $object);
        static::assertTrue($object->get('registrationOnlyCompanyRegistration'));
        static::assertSame(
            [
                'registrationOnlyCompanyRegistration' => true,
                'registrationTitle' => 'Business registration',
            ],
            $object->get('translated')
        );
    }

    public function testConstructDefaultsMissingCompanyRegistrationFlagToFalse(): void
    {
        $registration = new CustomerGroupEntity();
        $registration->setName('Test group');
        $registration->setTranslated([
            'registrationTitle' => 'Business registration',
        ]);

        $response = new CustomerGroupRegistrationSettingsRouteResponse($registration);
        $object = $response->getObject();

        static::assertInstanceOf(ArrayStruct::class, $object);
        static::assertFalse($object->get('registrationOnlyCompanyRegistration'));
        static::assertSame(
            [
                'registrationTitle' => 'Business registration',
                'registrationOnlyCompanyRegistration' => false,
            ],
            $object->get('translated')
        );
    }
}
