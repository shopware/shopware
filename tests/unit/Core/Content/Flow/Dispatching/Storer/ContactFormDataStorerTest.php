<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\ContactFormDataAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ContactFormDataStorer;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\ContactFormDataStorer
 */
class ContactFormDataStorerTest extends TestCase
{
    private ContactFormDataStorer $storer;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $this->storer = new ContactFormDataStorer();
    }

    public function testStoreAware(): void
    {
        $event = new ContactFormEvent(Context::createDefaultContext(), '', new MailRecipientStruct([]), new DataBag());
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ContactFormDataAware::CONTACT_FORM_DATA, $stored);
    }

    public function testStoreNotAware(): void
    {
        $event = $this->createMock(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ContactFormDataAware::CONTACT_FORM_DATA, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $contactFormData = ['test'];

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($contactFormData);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(ContactFormDataAware::CONTACT_FORM_DATA, $contactFormData);

        $this->storer->restore($storable);
    }

    public function testRestoreEmptyStored(): void
    {
        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(false);

        $storable->expects(static::never())
            ->method('getStore');

        $storable->expects(static::never())
            ->method('setData');

        $this->storer->restore($storable);
    }
}
