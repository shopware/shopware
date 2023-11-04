<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Validation;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory;
use Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('customer-order')]
class CustomerValidationFactoryTest extends TestCase
{
    /**
     * @dataProvider getCreateTestData
     */
    public function testCreate(
        DataValidationDefinition $profileDefinition,
        DataValidationDefinition $expected
    ): void {
        $customerProfileValidationFactory = $this
            ->getMockBuilder(CustomerProfileValidationFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $customerProfileValidationFactory
            ->method('create')
            ->willReturn($profileDefinition);

        $customerValidationFactory = new CustomerValidationFactory($customerProfileValidationFactory);
        $context = $this
            ->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $actual = $customerValidationFactory->create($context);

        static::assertEquals($expected, $actual);
    }

    public static function getCreateTestData(): iterable
    {
        $faker = Factory::create();

        // test with no constraints added
        $profileDefinition = new DataValidationDefinition();
        $expected = new DataValidationDefinition('customer.create');
        self::addConstraints($expected);

        yield [$profileDefinition, $expected];

        // test merge
        $profileDefinition->add('email', new Type('string'));
        $expected->set('email', new Type('string'), new NotBlank(), new Email());

        yield [$profileDefinition, $expected];

        // test with randomized data
        for ($i = 0; $i < 10; ++$i) {
            $profileDefinition = new DataValidationDefinition();

            $notBlankName = $faker->name();
            $profileDefinition->add($notBlankName, new NotBlank());

            $emailName = $faker->name();
            $profileDefinition->add($emailName, new Email());

            $expected = new DataValidationDefinition('customer.create');

            $expected->add($notBlankName, new NotBlank());
            $expected->add($emailName, new Email());

            self::addConstraints($expected);

            yield [$profileDefinition, $expected];
        }
    }

    /**
     * @see CustomerValidationFactory::addConstraints
     */
    private static function addConstraints(DataValidationDefinition $definition): void
    {
        $definition->add('email', new NotBlank(), new Email());
        $definition->add('active', new Type('boolean'));
    }
}
