<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageValidator::class)]
class LandingPageValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [LandingPageDefinition::class, LandingPageSalesChannelDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    public function testRejectsLandingPageWithoutAssignedSalesChannel(): void
    {
        $event = $this->createEvent([
            $this->createInsert(LandingPageDefinition::ENTITY_NAME, ['id' => Uuid::randomBytes()], '/landing-page'),
        ]);

        (new LandingPageValidator(Validation::createValidator()))->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('/landing-page/salesChannels', $exception->getViolations()->get(0)->getPropertyPath());
    }

    public function testAcceptsLandingPageWithAssignedSalesChannel(): void
    {
        $landingPageId = Uuid::randomBytes();
        $event = $this->createEvent([
            $this->createInsert(LandingPageDefinition::ENTITY_NAME, ['id' => $landingPageId], '/landing-page'),
            $this->createInsert(LandingPageSalesChannelDefinition::ENTITY_NAME, [
                'landing_page_id' => $landingPageId,
                'sales_channel_id' => Uuid::randomBytes(),
            ], '/landing-page/sales-channels'),
        ]);

        (new LandingPageValidator(Validation::createValidator()))->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param list<InsertCommand> $commands
     */
    private function createEvent(array $commands): PostWriteValidationEvent
    {
        return new PostWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands,
        );
    }

    /**
     * @param array<string, string> $primaryKey
     */
    private function createInsert(string $entityName, array $primaryKey, string $path): InsertCommand
    {
        return new InsertCommand(
            $this->definitionRegistry->getByEntityName($entityName),
            [],
            $primaryKey,
            EntityExistence::createEmpty(),
            $path,
        );
    }
}
