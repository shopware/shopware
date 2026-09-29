<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\NewsletterRecipientStorer;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterRecipientStorer::class)]
class NewsletterRecipientStorerTest extends TestCase
{
    private NewsletterRecipientStorer $storer;

    private NewsletterRecipientProvider&Stub $newsletterRecipientProvider;

    protected function setUp(): void
    {
        $this->newsletterRecipientProvider = static::createStub(NewsletterRecipientProvider::class);

        $this->storer = $this->createStorer($this->newsletterRecipientProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(NewsletterConfirmEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(CustomerRegisterEvent::class);
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

    public function testLazyLoadEntity(): void
    {
        $newsletterRecipientProvider = $this->createMock(NewsletterRecipientProvider::class);
        $storer = $this->createStorer($newsletterRecipientProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => 'id'], []);
        $storer->restore($storable);
        $entity = new NewsletterRecipientEntity();
        $entity->setId('id');
        $newsletterRecipientProvider->expects($this->once())->method('getData')->willReturn($entity);

        $res = $storable->getData('newsletterRecipient');
        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $newsletterRecipientProvider = $this->createMock(NewsletterRecipientProvider::class);
        $storer = $this->createStorer($newsletterRecipientProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => 'id'], []);
        $storer->restore($storable);
        $newsletterRecipientProvider->expects($this->once())->method('getData')->willReturn(null);

        $res = $storable->getData('newsletterRecipient');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('newsletterRecipient');

        static::assertNull($customerGroup);
    }

    private function createStorer(NewsletterRecipientProvider $newsletterRecipientProvider): NewsletterRecipientStorer
    {
        return new NewsletterRecipientStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $newsletterRecipientProvider,
        );
    }
}
