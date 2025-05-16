<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\PasswordValidationFactory;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[CoversClass(PasswordValidationFactory::class)]
class PasswordValidationFactoryTest extends TestCase
{
    private StaticSystemConfigService $systemConfigService;

    private PasswordValidationFactory $factory;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->factory = new PasswordValidationFactory($this->systemConfigService);
        $this->salesChannelContext = Generator::generateSalesChannelContext();
    }

    public function testCreateValidation(): void
    {
        $minLength = 10;
        $this->systemConfigService->set('core.loginRegistration.passwordMinLength', $minLength, $this->salesChannelContext->getSalesChannelId());

        $definition = $this->factory->create($this->salesChannelContext);

        static::assertInstanceOf(DataValidationDefinition::class, $definition);
        static::assertEquals('password.create', $definition->getName());

        $constraints = $definition->getProperties()['password'];
        static::assertCount(2, $constraints);
        static::assertContainsEquals(new NotBlank(), $constraints);
        static::assertContainsEquals(
            new Length(['min' => $minLength, 'max' => 4096, 'maxMessage' => 'VIOLATION::PASSWORD_IS_TOO_LONG']),
            $constraints
        );
    }

    public function testUpdateValidation(): void
    {
        $minLength = 10;
        $this->systemConfigService->set('core.loginRegistration.passwordMinLength', $minLength, $this->salesChannelContext->getSalesChannelId());

        $definition = $this->factory->update($this->salesChannelContext);

        static::assertInstanceOf(DataValidationDefinition::class, $definition);
        static::assertEquals('password.update', $definition->getName());

        $constraints = $definition->getProperties()['password'];
        static::assertCount(2, $constraints);
        static::assertContainsEquals(new NotBlank(), $constraints);
        static::assertContainsEquals(
            new Length(['min' => $minLength, 'max' => 4096, 'maxMessage' => 'VIOLATION::PASSWORD_IS_TOO_LONG']),
            $constraints
        );
    }
}
