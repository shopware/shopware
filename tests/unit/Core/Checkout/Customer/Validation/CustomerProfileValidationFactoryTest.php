<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
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

    private SalutationDefinition $salutationDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountTypes = [CustomerEntity::ACCOUNT_TYPE_BUSINESS, CustomerEntity::ACCOUNT_TYPE_PRIVATE];
        $this->salutationDefinition = new SalutationDefinition();
    }

    public function testCreateWithSalesChannelContext(): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $this->salutationDefinition,
            $this->createMock(SystemConfigService::class),
            $this->accountTypes,
        );

        $salesChannelContext = $this->mockSalesChannelContext();
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
            $this->salutationDefinition,
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->mockSalesChannelContext();
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
            $this->salutationDefinition,
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->mockSalesChannelContext();
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
            $this->salutationDefinition,
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->mockSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);
        $this->addConstraintsBirthday($expected);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContext(): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $this->salutationDefinition,
            $this->createMock(SystemConfigService::class),
            $this->accountTypes,
        );

        $salesChannelContext = $this->mockSalesChannelContext();
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
            $this->salutationDefinition,
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->mockSalesChannelContext();
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
            $this->salutationDefinition,
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->mockSalesChannelContext();
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
            $this->salutationDefinition,
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->mockSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);
        $this->addConstraintsBirthday($expected);

        static::assertEquals($expected, $actual);
    }

    private function mockSalesChannelContext(): SalesChannelContext&MockObject
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $context = Context::createDefaultContext();

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        return $salesChannelContext;
    }

    private function addConstraintsSalesChannelContext(DataValidationDefinition $definition, SalesChannelContext $context): void
    {
        $definition->add('salutationId', new EntityExists(['entity' => $this->salutationDefinition->getEntityName(), 'context' => $context->getContext()]))
            ->add('firstName', new NotBlank())
            ->add('lastName', new NotBlank())
            ->add('accountType', new Choice($this->accountTypes));
    }

    private function addConstraintsBirthday(DataValidationDefinition $definition): void
    {
        $definition
            ->add('birthdayDay', new GreaterThanOrEqual(['value' => 1]), new LessThanOrEqual(['value' => 31]))
            ->add('birthdayMonth', new GreaterThanOrEqual(['value' => 1]), new LessThanOrEqual(['value' => 12]))
            ->add('birthdayYear', new GreaterThanOrEqual(['value' => 1900]), new LessThanOrEqual(['value' => date('Y')]));
    }
}
