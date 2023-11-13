<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\NewsletterRecipientStorer;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\NewsletterRecipientStorer
 */
class NewsletterRecipientStorerTest extends TestCase
{
    private NewsletterRecipientStorer $storer;

    private MockObject&EntityRepository $repository;

    private MockObject&EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->storer = new NewsletterRecipientStorer($this->repository, $this->dispatcher);
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(NewsletterConfirmEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('newsletterRecipient', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLoadEntity(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);
        $entity = new NewsletterRecipientEntity();
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $res = $this->storer->load(['3443', Context::createDefaultContext()]);

        static::assertEquals($res, $entity);
    }

    public function testLoadNullEntity(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);
        $entity = null;
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $res = $this->storer->load(['3443', Context::createDefaultContext()]);

        static::assertEquals($res, $entity);
    }

    public function testLazyLoadEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => 'id'], []);
        $this->storer->restore($storable);
        $entity = new NewsletterRecipientEntity();
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $res = $storable->getData('newsletterRecipient');
        static::assertEquals($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => 'id'], []);
        $this->storer->restore($storable);
        $entity = null;
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $res = $storable->getData('newsletterRecipient');

        static::assertEquals($res, $entity);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('newsletterRecipient');

        static::assertNull($customerGroup);
    }

    public function testDispatchBeforeLoadStorableFlowDataEvent(): void
    {
        $this->dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(BeforeLoadStorableFlowDataEvent::class),
                'flow.storer.newsletter_recipient.criteria.event'
            );

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => 'id'], []);
        $this->storer->restore($storable);
        $storable->getData('newsletterRecipient');
    }
}
