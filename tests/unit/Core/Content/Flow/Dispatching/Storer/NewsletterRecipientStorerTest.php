<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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

    private NewsletterRecipientProvider&MockObject $newsletterRecipientProvider;

    protected function setUp(): void
    {
        $this->newsletterRecipientProvider = $this->createMock(NewsletterRecipientProvider::class);

        $this->storer = new NewsletterRecipientStorer(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->newsletterRecipientProvider
        );
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

    public function testLazyLoadEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => 'id'], []);
        $this->storer->restore($storable);
        $entity = new NewsletterRecipientEntity();
        $entity->setId('id');
        $this->newsletterRecipientProvider->expects($this->once())->method('getData')->willReturn($entity);

        $res = $storable->getData('newsletterRecipient');
        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['newsletterRecipientId' => 'id'], []);
        $this->storer->restore($storable);
        $this->newsletterRecipientProvider->expects($this->once())->method('getData')->willReturn(null);

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
}
