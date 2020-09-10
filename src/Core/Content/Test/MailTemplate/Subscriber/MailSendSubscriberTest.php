<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

class MailSendSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider sendMailProvider
     */
    public function testSendMail(bool $skip, ?array $recipients, array $expectedRecipients): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();
        if ($skip) {
            $context->addExtension(MailSendSubscriber::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(true, [], []));
        }
        $mailTemplateId = $this->getContainer()
            ->get('mail_template.repository')
            ->searchIds($criteria, $context)
            ->firstId();

        static::assertNotEmpty($mailTemplateId);

        $config = array_filter([
            'mail_template_id' => $mailTemplateId,
            'recipients' => $recipients,
        ]);

        $event = new ContactFormEvent($context, Defaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());

        $mailService = new TestMailService();
        $subscriber = new MailSendSubscriber(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get('logger')
        );

        $subscriber->sendMail(new BusinessEvent('test', $event, $config));

        if ($skip) {
            static::assertEquals(0, $mailService->calls);
            static::assertNull($mailService->data);
        } else {
            static::assertEquals(1, $mailService->calls);
            static::assertEquals($mailService->data['recipients'], $expectedRecipients);
        }
    }

    public function sendMailProvider(): iterable
    {
        yield 'Test skip mail' => [true, null, ['test@example.com' => 'Shopware ag']];
        yield 'Test send mail' => [false, null, ['test@example.com' => 'Shopware ag']];
        yield 'Test overwrite recipients' => [false, ['test2@example.com' => 'Overwrite'], ['test2@example.com' => 'Overwrite']];
    }
}

class TestMailService extends MailService
{
    public $calls = 0;

    public $data = null;

    public function __construct()
    {
    }

    public function send(array $data, Context $context, array $templateData = []): ?\Swift_Message
    {
        $this->data = $data;
        ++$this->calls;

        return null;
    }
}
