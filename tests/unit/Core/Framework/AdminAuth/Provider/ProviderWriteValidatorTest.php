<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Entity\Provider\AdminAuthProviderDefinition;
use Shopware\Core\Framework\AdminAuth\Provider\ConfigProviderLoader;
use Shopware\Core\Framework\AdminAuth\Provider\DalProviderLoader;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderRegistry;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderWriteValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(ProviderWriteValidator::class)]
class ProviderWriteValidatorTest extends TestCase
{
    public function testRejectsProviderWritesWhenManagedByConfig(): void
    {
        $event = $this->createEvent([$this->command(AdminAuthProviderDefinition::ENTITY_NAME)]);

        $this->createValidator(managedByConfig: true, adminUi: true)->preValidate($event);

        $this->assertProviderViolation($event);
    }

    public function testRejectsProviderWritesWhenAdminUiIsDisabled(): void
    {
        $event = $this->createEvent([$this->command(AdminAuthProviderDefinition::ENTITY_NAME)]);

        $this->createValidator(managedByConfig: false, adminUi: false)->preValidate($event);

        $this->assertProviderViolation($event);
    }

    public function testAllowsProviderWritesWhenTheAdminUiManagesProviders(): void
    {
        $event = $this->createEvent([$this->command(AdminAuthProviderDefinition::ENTITY_NAME)]);

        $this->createValidator(managedByConfig: false, adminUi: true)->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresWritesOnOtherEntities(): void
    {
        $event = $this->createEvent([$this->command('product')]);

        $this->createValidator(managedByConfig: true, adminUi: true)->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[DisabledFeatures(['ADMIN_AUTH'])]
    public function testDoesNothingWhenTheFeatureIsInactive(): void
    {
        $event = $this->createEvent([$this->command(AdminAuthProviderDefinition::ENTITY_NAME)]);

        $this->createValidator(managedByConfig: true, adminUi: true)->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    private function createValidator(bool $managedByConfig, bool $adminUi): ProviderWriteValidator
    {
        $yamlProviders = $managedByConfig
            ? ['corp_okta' => ['label' => 'SSO', 'client_id' => 'a', 'client_secret' => 'b']]
            : [];

        $registry = new ProviderRegistry(
            new ConfigProviderLoader($yamlProviders),
            $this->createMock(DalProviderLoader::class),
            $adminUi
        );

        return new ProviderWriteValidator($registry);
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function createEvent(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands
        );
    }

    private function command(string $entityName): WriteCommand
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('getEntityName')->willReturn($entityName);

        return $command;
    }

    private function assertProviderViolation(PreWriteValidationEvent $event): void
    {
        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);

        $exception = $exceptions[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(
            AdminAuthException::PROVIDER_NOT_WRITABLE,
            $exception->getViolations()->get(0)->getCode()
        );
    }
}
