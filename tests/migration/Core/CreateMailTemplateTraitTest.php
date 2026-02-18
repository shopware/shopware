<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopware\Core\Migration\Structs\MailTemplateTypeCreateStruct;
use Shopware\Core\Migration\Traits\CreateMailTemplateTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CreateMailTemplateTrait::class)]
class CreateMailTemplateTraitTest extends TestCase
{
    use CreateMailTemplateTrait;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const TEST_TECHNICAL_NAME = 'TEST_MAIL_TEMPLATE';

    private Connection $connection;

    private string $testDirectoryName;

    private string $targetDirectory;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->testDirectoryName = Uuid::randomHex();

        $this->filesystem = new Filesystem();
        $this->targetDirectory = __DIR__ . '/../../../src/Core/Migration/Fixtures/mails/' . $this->testDirectoryName;
        $this->filesystem->mkdir($this->targetDirectory);

        $this->filesystem->touch([
            $this->targetDirectory . '/en-html.html.twig',
            $this->targetDirectory . '/en-plain.html.twig',
            $this->targetDirectory . '/en-plain.txt.twig',
            $this->targetDirectory . '/de-html.html.twig',
            $this->targetDirectory . '/de-plain.html.twig',
            $this->targetDirectory . '/de-plain.txt.twig',
        ]);

        $this->filesystem->appendToFile($this->targetDirectory . '/en-html.html.twig', '<h1>en-html.html.twig content</h1>');
        $this->filesystem->appendToFile($this->targetDirectory . '/en-plain.html.twig', 'en-plain.html.twig content');
        $this->filesystem->appendToFile($this->targetDirectory . '/en-plain.txt.twig', 'en-plain.txt.twig content');
        $this->filesystem->appendToFile($this->targetDirectory . '/de-html.html.twig', '<h1>de-html.html.twig content</h1>');
        $this->filesystem->appendToFile($this->targetDirectory . '/de-plain.html.twig', 'de-plain.html.twig content');
        $this->filesystem->appendToFile($this->targetDirectory . '/de-plain.txt.twig', 'de-plain.txt.twig content');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove([$this->targetDirectory]);
    }

    public function testCreateMail(): void
    {
        $enLanguageByteId = $this->getLanguageIdByLocale($this->connection, 'en-GB');
        static::assertIsString($enLanguageByteId);
        $deLanguageByteId = $this->getLanguageIdByLocale($this->connection, 'de-DE');
        static::assertIsString($deLanguageByteId);

        // create new mail template
        $mailTemplateType = new MailTemplateTypeCreateStruct(
            self::TEST_TECHNICAL_NAME,
            'EN test name',
            'DE Test Name',
        );

        $mailTemplate = new MailTemplateCreateStruct(
            $this->testDirectoryName,
            'EN test name',
            'DE Test Name',
            'Test description',
            'Test Beschreibung',
            '{{ salesChannel.name }}',
            '{{ salesChannel.name }}',
        );

        // Execute twice to check there is no duplicate
        $this->createMail($this->connection, $mailTemplateType, $mailTemplate);
        $this->createMail($this->connection, $mailTemplateType, $mailTemplate);

        $mailTemplateTypes = $this->getMailTemplateTypes();
        static::assertCount(1, $mailTemplateTypes);
        static::assertArrayHasKey('id', $mailTemplateTypes[0]);
        static::assertArrayHasKey('translations', $mailTemplateTypes[0]);
        $mailTemplateTypeTranslations = $mailTemplateTypes[0]['translations'];

        static::assertCount(2, $mailTemplateTypeTranslations);

        $enTypeTranslation = $this->findTranslationByLanguageId($enLanguageByteId, $mailTemplateTypeTranslations);
        static::assertArrayHasKey('name', $enTypeTranslation);
        static::assertSame($mailTemplateType->getEnName(), $enTypeTranslation['name']);
        $deTypeTranslation = $this->findTranslationByLanguageId($deLanguageByteId, $mailTemplateTypeTranslations);
        static::assertArrayHasKey('name', $deTypeTranslation);
        static::assertSame($mailTemplateType->getDeName(), $deTypeTranslation['name']);

        $mailTemplates = $this->getMailTemplates($mailTemplateTypes[0]['id']);
        static::assertCount(1, $mailTemplates);
        static::assertArrayHasKey('translations', $mailTemplates[0]);
        $mailTemplateTranslations = $mailTemplates[0]['translations'];
        static::assertCount(2, $mailTemplateTranslations);

        $enMailTranslation = $this->findTranslationByLanguageId($enLanguageByteId, $mailTemplateTranslations);
        static::assertArrayHasKey('sender_name', $enMailTranslation);
        static::assertArrayHasKey('subject', $enMailTranslation);
        static::assertArrayHasKey('description', $enMailTranslation);
        static::assertArrayHasKey('content_html', $enMailTranslation);
        static::assertArrayHasKey('content_plain', $enMailTranslation);

        static::assertSame($mailTemplate->getEnSenderName(), $enMailTranslation['sender_name']);
        static::assertSame($mailTemplate->getEnSubject(), $enMailTranslation['subject']);
        static::assertSame($mailTemplate->getEnDescription(), $enMailTranslation['description']);
        static::assertSame($mailTemplate->getEnHtml(), $enMailTranslation['content_html']);
        static::assertSame($this->filesystem->readFile($this->targetDirectory . '/en-plain.txt.twig'), $enMailTranslation['content_plain']);

        $deMailTranslation = $this->findTranslationByLanguageId($deLanguageByteId, $mailTemplateTranslations);
        static::assertArrayHasKey('sender_name', $deMailTranslation);
        static::assertArrayHasKey('subject', $deMailTranslation);
        static::assertArrayHasKey('description', $deMailTranslation);
        static::assertArrayHasKey('content_html', $deMailTranslation);
        static::assertArrayHasKey('content_plain', $deMailTranslation);

        static::assertSame($mailTemplate->getDeSenderName(), $deMailTranslation['sender_name']);
        static::assertSame($mailTemplate->getDeSubject(), $deMailTranslation['subject']);
        static::assertSame($mailTemplate->getDeDescription(), $deMailTranslation['description']);
        static::assertSame($mailTemplate->getDeHtml(), $deMailTranslation['content_html']);
        static::assertSame($this->filesystem->readFile($this->targetDirectory . '/de-plain.txt.twig'), $deMailTranslation['content_plain']);
    }

    public function testCreateMailWithoutTxtFiles(): void
    {
        $this->filesystem->remove([
            $this->targetDirectory . '/en-plain.txt.twig',
            $this->targetDirectory . '/de-plain.txt.twig',
        ]);

        $enLanguageByteId = $this->getLanguageIdByLocale($this->connection, 'en-GB');
        static::assertIsString($enLanguageByteId);
        $deLanguageByteId = $this->getLanguageIdByLocale($this->connection, 'de-DE');
        static::assertIsString($deLanguageByteId);

        // create new mail template
        $mailTemplateType = new MailTemplateTypeCreateStruct(
            self::TEST_TECHNICAL_NAME,
            'EN test name',
            'DE Test Name',
        );

        $mailTemplate = new MailTemplateCreateStruct(
            $this->testDirectoryName,
            'EN test name',
            'DE Test Name',
            'Test description',
            'Test Beschreibung',
            '{{ salesChannel.name }}',
            '{{ salesChannel.name }}',
        );

        $this->createMail($this->connection, $mailTemplateType, $mailTemplate);

        $mailTemplateTypes = $this->getMailTemplateTypes();
        static::assertCount(1, $mailTemplateTypes);
        static::assertArrayHasKey('id', $mailTemplateTypes[0]);

        $mailTemplates = $this->getMailTemplates($mailTemplateTypes[0]['id']);
        static::assertCount(1, $mailTemplates);
        static::assertArrayHasKey('translations', $mailTemplates[0]);
        $mailTemplateTranslations = $mailTemplates[0]['translations'];

        $enMailTranslation = $this->findTranslationByLanguageId($enLanguageByteId, $mailTemplateTranslations);
        static::assertArrayHasKey('sender_name', $enMailTranslation);
        static::assertArrayHasKey('subject', $enMailTranslation);
        static::assertArrayHasKey('description', $enMailTranslation);
        static::assertArrayHasKey('content_html', $enMailTranslation);
        static::assertArrayHasKey('content_plain', $enMailTranslation);

        static::assertSame($mailTemplate->getEnSenderName(), $enMailTranslation['sender_name']);
        static::assertSame($mailTemplate->getEnSubject(), $enMailTranslation['subject']);
        static::assertSame($mailTemplate->getEnDescription(), $enMailTranslation['description']);
        static::assertSame($mailTemplate->getEnHtml(), $enMailTranslation['content_html']);
        static::assertSame($this->filesystem->readFile($this->targetDirectory . '/en-plain.html.twig'), $enMailTranslation['content_plain']);

        $deMailTranslation = $this->findTranslationByLanguageId($deLanguageByteId, $mailTemplateTranslations);
        static::assertArrayHasKey('sender_name', $deMailTranslation);
        static::assertArrayHasKey('subject', $deMailTranslation);
        static::assertArrayHasKey('description', $deMailTranslation);
        static::assertArrayHasKey('content_html', $deMailTranslation);
        static::assertArrayHasKey('content_plain', $deMailTranslation);

        static::assertSame($mailTemplate->getDeSenderName(), $deMailTranslation['sender_name']);
        static::assertSame($mailTemplate->getDeSubject(), $deMailTranslation['subject']);
        static::assertSame($mailTemplate->getDeDescription(), $deMailTranslation['description']);
        static::assertSame($mailTemplate->getDeHtml(), $deMailTranslation['content_html']);
        static::assertSame($this->filesystem->readFile($this->targetDirectory . '/de-plain.html.twig'), $deMailTranslation['content_plain']);
    }

    /**
     * @param array<array<string, mixed>> $translations
     *
     * @return array<string, mixed>
     */
    private function findTranslationByLanguageId(string $languageByteId, array $translations): array
    {
        foreach ($translations as $translation) {
            if ($translation['language_id'] === $languageByteId) {
                return $translation;
            }
        }

        static::fail('Could not find translation for language ' . Uuid::fromBytesToHex($languageByteId));
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getMailTemplates(string $mailTemplateTypeByteId): array
    {
        $mailTemplates = $this->connection->fetchAllAssociative(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId]
        );

        foreach ($mailTemplates as &$mailTemplate) {
            $mailTemplate['translations'] = $this->connection->fetchAllAssociative(
                'SELECT * FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId',
                ['mailTemplateId' => $mailTemplate['id']]
            );
        }

        return $mailTemplates;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getMailTemplateTypes(): array
    {
        $mailTemplateTypes = $this->connection->fetchAllAssociative(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => self::TEST_TECHNICAL_NAME]
        );

        foreach ($mailTemplateTypes as &$mailTemplateType) {
            $mailTemplateType['translations'] = $this->connection->fetchAllAssociative(
                'SELECT * FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId',
                ['mailTemplateTypeId' => $mailTemplateType['id']]
            );
        }

        return $mailTemplateTypes;
    }
}
