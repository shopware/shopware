<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerProfileValidationFactory::class)]
class CustomerProfileValidationFactoryTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $accountTypes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountTypes = [CustomerEntity::ACCOUNT_TYPE_BUSINESS, CustomerEntity::ACCOUNT_TYPE_PRIVATE];
    }

    public function testCreateWithSalesChannelContext(): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithSalesChannelContextButBirthdayFieldIsHidden(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => ['core.loginRegistration.showBirthdayField' => false],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithSalesChannelContextButBirthdayFieldIsOptional(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => false,
            ],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithSalesChannelContextButBirthdayFieldIsRequired(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => true,
            ],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);
        $this->addConstraintsBirthday($expected);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContext(): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContextButBirthdayFieldIsHidden(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => ['core.loginRegistration.showBirthdayField' => false],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContextButBirthdayFieldIsOptional(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => false,
            ],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContextButBirthdayFieldIsRequired(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => true,
            ],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);
        $this->addConstraintsBirthday($expected);

        static::assertEquals($expected, $actual);
    }

    #[DataProvider('nameFieldProvider')]
    public function testCreateTrimsNameFieldBeforeBlankCheck(string $field): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $definition = $customerProfileValidationFactory->create($this->getSalesChannelContext());

        $this->assertBlankCheckTrims($definition, $field);
    }

    #[DataProvider('nameFieldProvider')]
    public function testUpdateTrimsNameFieldBeforeBlankCheck(string $field): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $definition = $customerProfileValidationFactory->update($this->getSalesChannelContext());

        $this->assertBlankCheckTrims($definition, $field);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nameFieldProvider(): iterable
    {
        yield 'firstName' => ['firstName'];
        yield 'lastName' => ['lastName'];
    }

    private function assertBlankCheckTrims(DataValidationDefinition $definition, string $field): void
    {
        $notBlank = $definition->getProperty($field)[0] ?? null;
        static::assertInstanceOf(NotBlank::class, $notBlank);

        $normalizer = $notBlank->normalizer;
        static::assertIsCallable($normalizer);
        static::assertSame('', $normalizer('   '));
        static::assertSame('Ada', $normalizer('  Ada  '));

        // HappyPathValidator applies the normalizer without checking the type first
        static::assertNull($normalizer(null));
    }

    /**
     * @return callable(mixed): mixed
     */
    private static function trimNormalizer(): callable
    {
        return static fn (mixed $value): mixed => \is_string($value) ? trim($value) : $value;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannel,
        );
    }

    private function addConstraintsSalesChannelContext(DataValidationDefinition $definition, SalesChannelContext $context): void
    {
        $definition
            ->add('salutationId', new EntityExists(entity: SalutationDefinition::ENTITY_NAME, context: $context->getContext()))
            ->add('firstName', new NotBlank(normalizer: self::trimNormalizer()))
            ->add('lastName', new NotBlank(normalizer: self::trimNormalizer()))
            ->add('accountType', new Choice(choices: $this->accountTypes))
            ->add('title', new Length(max: CustomerDefinition::MAX_LENGTH_TITLE))
            ->add('firstName', new Length(max: CustomerDefinition::MAX_LENGTH_FIRST_NAME))
            ->add('lastName', new Length(max: CustomerDefinition::MAX_LENGTH_LAST_NAME));
    }

    private function addConstraintsBirthday(DataValidationDefinition $definition): void
    {
        $definition
            ->add('birthdayDay', new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 31))
            ->add('birthdayMonth', new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 12))
            ->add('birthdayYear', new GreaterThanOrEqual(value: 1900), new LessThanOrEqual(value: date('Y')));
    }
}
