<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mime\Email;

/**
 * @group slow
 */
class MailActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use MediaFixtures;

    private const MEDIA_FIXTURE = __DIR__ . '/../../Media/fixtures/Shopware_5_3_Broschuere.pdf';

    public function setUp(): void
    {
        static::markTestSkipped('to heavy memory usage - if you changed something for mails, run this');
        parent::setUp();
    }

    public function testSendingSimpleTestMail(): void
    {
        $data = $this->getTestData();

        $this->getBrowser()->request('POST', '/api/_action/mail-template/send', $data);

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $messages = $mailCollector->getEvents()->getMessages();
        static::assertGreaterThan(0, \count($messages));
        /** @var Email $message */
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(Email::class, $message);
        static::assertSame('My precious subject', $message->getSubject());
        static::assertSame(
            'doNotReply@localhost.com',
            current($message->getFrom())->getAddress(),
            print_r($message->getFrom(), true)
        );
        static::assertSame('No Reply', current($message->getFrom())->getName(), print_r($message->getFrom(), true));
        static::assertSame(
            'recipient@example.com',
            current($message->getTo())->getAddress(),
            print_r($message->getFrom(), true)
        );

        $partsByType = [];
        $partsByType['text/plain'] = $message->getTextBody();
        $partsByType['text/html'] = $message->getHtmlBody();

        static::assertSame('This is plain text', $partsByType['text/plain']);
        static::assertSame('<h1>This is HTML</h1>', $partsByType['text/html']);
    }

    public function testSendingMailWithAttachments(): void
    {
        $data = $this->getTestDataWithAttachments();

        $this->getBrowser()->request('POST', '/api/_action/mail-template/send', $data);

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $messages = $mailCollector->getEvents()->getMessages();
        static::assertGreaterThan(0, \count($messages));
        /** @var Email $message */
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(Email::class, $message);

        $partsByType = [];
        $partsByType['application/pdf'] = $message->getAttachments()[0];

        static::assertArrayHasKey('application/pdf', $partsByType);

        // Use strcmp() for binary safety
        static::assertSame(0, strcmp($partsByType['application/pdf']->getBody(), file_get_contents(self::MEDIA_FIXTURE)));
    }

    public function testSendingMailWithFooterAndHeader(): void
    {
        $data = $this->getTestDataWithHeaderAndFooter();

        $this->getBrowser()->request('POST', '/api/_action/mail-template/send', $data);

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $messages = $mailCollector->getEvents()->getMessages();
        static::assertGreaterThan(0, \count($messages));
        /** @var Email $message */
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(Email::class, $message);

        $partsByType = [];
        $partsByType['text/plain'] = $message->getTextBody();
        $partsByType['text/html'] = $message->getHtmlBody();

        static::assertSame('Header This is plain text Footer', $partsByType['text/plain']);
        static::assertSame('<h1>Header</h1> <h1>This is HTML</h1> <h1>Footer</h1>', $partsByType['text/html']);
    }

    public function testBuildingRenderedMailTemplate(): void
    {
        $data = $this->getTestDataWithMailTemplateType();

        $this->getBrowser()->request('POST', '/api/_action/mail-template/build', $data);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertSame('<h1>This is HTML</h1>', json_decode($this->getBrowser()->getResponse()->getContent()));
    }

    private function getTestData(): array
    {
        return [
            'recipients' => ['recipient@example.com' => 'Recipient'],
            'contentPlain' => 'This is plain text',
            'contentHtml' => '<h1>This is HTML</h1>',
            'subject' => 'My precious subject',
            'senderName' => 'No Reply',
            'mediaIds' => [],
            'salesChannelId' => Defaults::SALES_CHANNEL,
        ];
    }

    private function getTestDataWithMailTemplateType(): array
    {
        $testData['mailTemplateType'] = [
            'templateData' => [
                'salesChannel' => [
                    'id' => Defaults::SALES_CHANNEL,
                ],
            ],
        ];
        $testData['mailTemplate'] = [
            'contentPlain' => 'This is plain text',
            'contentHtml' => '<h1>This is HTML</h1>',
        ];

        return $testData;
    }

    private function getTestDataWithAttachments(): array
    {
        $testData = $this->getTestData();
        $mediaFixture = $this->preparePdfMediaFixture();
        $testData['mediaIds'] = [$mediaFixture->getId()];

        return $testData;
    }

    private function getTestDataWithHeaderAndFooter(): array
    {
        $testData = $this->getTestData();
        $this->createHeaderAndFooter();

        return $testData;
    }

    private function preparePdfMediaFixture(): MediaEntity
    {
        $mediaFixture = $this->getPdf();
        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $this->getPublicFilesystem()->put(
            $urlGenerator->getRelativeMediaUrl($mediaFixture),
            file_get_contents(self::MEDIA_FIXTURE)
        );

        return $mediaFixture;
    }

    private function createHeaderAndFooter(): void
    {
        $headerFooterRepository = $this->getContainer()->get('mail_header_footer.repository');

        $data = [
            'id' => Uuid::randomHex(),
            'systemDefault' => true,
            'name' => 'Test-Template',
            'description' => 'John Doe',
            'headerPlain' => 'Header ',
            'headerHtml' => '<h1>Header</h1> ',
            'footerPlain' => ' Footer',
            'footerHtml' => ' <h1>Footer</h1>',
            'salesChannels' => [
                [
                    'id' => Defaults::SALES_CHANNEL,
                ],
            ],
        ];

        $headerFooterRepository->create([$data], Context::createDefaultContext());
    }

    private function getProfiler(): Profiler
    {
        return $this->getBrowser()->getContainer()->get('profiler');
    }
}
