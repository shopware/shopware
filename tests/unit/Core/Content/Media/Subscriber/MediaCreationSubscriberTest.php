<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Subscriber\MediaCreationSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(MediaCreationSubscriber::class)]
class MediaCreationSubscriberTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testSubscribedEvents(): void
    {
        static::assertEquals(
            [
                EntityWriteEvent::class => 'beforeWrite',
            ],
            MediaCreationSubscriber::getSubscribedEvents()
        );
    }

    public function getDefinition(): MediaDefinition
    {
        new StaticDefinitionInstanceRegistry(
            [$definition = new MediaDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        return $definition;
    }

    public function testBeforeWriteOnlyReactsToLiveVersions(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId($this->ids->create('version'));

        $subscriber = new MediaCreationSubscriber();

        $definition = $this->getDefinition();

        $command = new InsertCommand(
            $definition,
            ['path' => 'media/Bildschirm­foto 2023-06-24 um 16.30.36.png'],
            ['id' => $this->ids->getBytes('media-1')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->beforeWrite($event);

        static::assertSame('media/Bildschirmfoto 2023-06-24 um 16.30.36.png', $command->getPayload()['path']);
    }

    public function testPathIsReplacedOnInsert(): void
    {
        $context = Context::createDefaultContext();

        $subscriber = new MediaCreationSubscriber();

        $definition = $this->getDefinition();

        $command = new InsertCommand(
            $definition,
            ['path' => 'media/Bildschirm­foto 2023-06-24 um 16.30.36.png'],
            ['id' => $this->ids->getBytes('media-1')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->beforeWrite($event);

        static::assertSame('media/Bildschirmfoto 2023-06-24 um 16.30.36.png', $command->getPayload()['path']);
    }

    public function testPathIsReplacedOnUpdate(): void
    {
        $context = Context::createDefaultContext();

        $subscriber = new MediaCreationSubscriber();

        $definition = $this->getDefinition();

        $command = new UpdateCommand(
            $definition,
            ['path' => 'media/Bildschirmfoto 2023-06-24 um 16.30.36.png'],
            ['id' => $this->ids->getBytes('media-1')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->beforeWrite($event);

        static::assertSame('media/Bildschirmfoto 2023-06-24 um 16.30.36.png', $command->getPayload()['path']);
    }
}
