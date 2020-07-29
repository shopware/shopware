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
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\SwiftmailerBundle\DataCollector\MessageDataCollector;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class MailActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use MediaFixtures;

    private const MEDIA_FIXTURE = __DIR__ . '/../../Media/fixtures/Shopware_5_3_Broschuere.pdf';

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testSendingSimpleTestMail(): void
    {
        $data = $this->getTestData();

        $this->getProfiler()->enable();
        $this->getBrowser()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/mail-template/send', $data);
        $this->getProfiler()->disable();

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('swiftmailer');

        // checks that an email was sent
        $messages = $mailCollector->getMessages();
        static::assertGreaterThan(0, count($messages));
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(\Swift_Message::class, $message);
        static::assertSame('My precious subject', $message->getSubject());
        static::assertSame('doNotReply@localhost', key($message->getFrom()));
        static::assertSame('No Reply', current($message->getFrom()));
        static::assertSame('recipient@example.com', key($message->getTo()));

        $partsByType = [];
        foreach ($message->getChildren() as $contentPart) {
            $partsByType[$contentPart->getContentType()] = $contentPart->getBody();
        }

        static::assertArrayHasKey('text/plain', $partsByType);
        static::assertArrayHasKey('text/html', $partsByType);
        static::assertSame('This is plain text', $partsByType['text/plain']);
        static::assertSame('<h1>This is HTML</h1>', $partsByType['text/html']);
    }

    public function testSendingMailWithAttachments(): void
    {
        $data = $this->getTestDataWithAttachments();

        $this->getProfiler()->enable();
        $this->getBrowser()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/mail-template/send', $data);
        $this->getProfiler()->disable();

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('swiftmailer');

        // checks that an email was sent
        $messages = $mailCollector->getMessages();
        static::assertGreaterThan(0, count($messages));
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(\Swift_Message::class, $message);

        $partsByType = [];
        foreach ($message->getChildren() as $contentPart) {
            $partsByType[$contentPart->getContentType()] = $contentPart->getBody();
        }

        static::assertArrayHasKey('application/pdf', $partsByType);

        // Use strcmp() for binary safety
        static::assertSame(0, strcmp($partsByType['application/pdf'], file_get_contents(self::MEDIA_FIXTURE)));
    }

    public function testSendingMailWithFooterAndHeader(): void
    {
        $data = $this->getTestDataWithHeaderAndFooter();

        $this->getProfiler()->enable();
        $this->getBrowser()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/mail-template/send', $data);
        $this->getProfiler()->disable();

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('swiftmailer');

        // checks that an email was sent
        $messages = $mailCollector->getMessages();
        static::assertGreaterThan(0, count($messages));
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(\Swift_Message::class, $message);

        $partsByType = [];
        foreach ($message->getChildren() as $contentPart) {
            $partsByType[$contentPart->getContentType()] = $contentPart->getBody();
        }

        static::assertArrayHasKey('text/plain', $partsByType);
        static::assertArrayHasKey('text/html', $partsByType);
        static::assertSame('Header This is plain text Footer', $partsByType['text/plain']);
        static::assertSame('<h1>Header</h1> <h1>This is HTML</h1> <h1>Footer</h1>', $partsByType['text/html']);
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
