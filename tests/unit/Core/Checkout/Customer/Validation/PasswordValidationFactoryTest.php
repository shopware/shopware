<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\PasswordValidationFactory;
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

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->factory = new PasswordValidationFactory($this->systemConfigService);
    }

    public function testCreateValidation(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->systemConfigService->set('core.loginRegistration.passwordMinLength', 10, $salesChannelContext->getSalesChannelId());

        $definition = $this->factory->create($salesChannelContext);

        static::assertSame('password.create', $definition->getName());
        $constraints = $definition->getProperties()['password'];
        static::assertCount(2, $constraints);
        static::assertContainsEquals(new NotBlank(), $constraints);
        static::assertContainsEquals(
            new Length(['min' => 10, 'max' => 4096, 'maxMessage' => 'VIOLATION::PASSWORD_IS_TOO_LONG']),
            $constraints
        );
    }

    public function testUpdateValidation(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->systemConfigService->set('core.loginRegistration.passwordMinLength', 10, $salesChannelContext->getSalesChannelId());

        $definition = $this->factory->update($salesChannelContext);

        static::assertSame('password.update', $definition->getName());
        $constraints = $definition->getProperties()['password'];
        static::assertCount(2, $constraints);
        static::assertContainsEquals(new NotBlank(), $constraints);
        static::assertContainsEquals(
            new Length(['min' => 10, 'max' => 4096, 'maxMessage' => 'VIOLATION::PASSWORD_IS_TOO_LONG']),
            $constraints
        );
    }
}
