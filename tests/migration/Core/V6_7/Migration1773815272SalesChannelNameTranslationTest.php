<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1773815272SalesChannelNameTranslation;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1773815272SalesChannelNameTranslation::class)]
class Migration1773815272SalesChannelNameTranslationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private string $mailTemplateByteId;

    private string $mailTemplateTypeByteId;

    private string $languageByteId;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->mailTemplateByteId = Uuid::randomBytes();
        $this->mailTemplateTypeByteId = Uuid::randomBytes();
        $this->languageByteId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773815272, (new Migration1773815272SalesChannelNameTranslation())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->prepareTestMailTemplate();

        $migration = new Migration1773815272SalesChannelNameTranslation();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $translations = $this->getMailTemplateTranslations();
        static::assertIsArray($translations);
        static::assertNotCount(0, $translations);

        foreach ($translations as $translation) {
            static::assertStringNotContainsString('{{ salesChannel.name }}', $translation['sender_name']);
            static::assertStringNotContainsString('{{ salesChannel.name }}', $translation['subject']);
            static::assertStringNotContainsString('{{ salesChannel.name }}', $translation['content_html']);
            static::assertStringNotContainsString('{{ salesChannel.name }}', $translation['content_plain']);
        }

        $expected = 'test {{ salesChannel.translated.name }}';

        $expectedContentHtml = <<<html
<h1>Hello from {{ salesChannel.translated.name }}</h1>
<p>This is another test for replace {{ salesChannel.translated.name }}.</p>
<p>And another one {{ salesChannel.translated.name }}</p>
html;

        $expectedContentPlain = <<<EOD
Hello from {{ salesChannel.translated.name }}

This is another test for replace {{ salesChannel.translated.name }}.

And another one {{ salesChannel.translated.name }}
EOD;

        $testTranslation = $this->getTestTranslation();
        static::assertIsArray($testTranslation);
        static::assertNotCount(0, $testTranslation);

        static::assertSame($expected, $testTranslation['sender_name']);
        static::assertSame($expected, $testTranslation['subject']);
        static::assertSame($expectedContentHtml, $testTranslation['content_html']);
        static::assertSame($expectedContentPlain, $testTranslation['content_plain']);
    }

    private function prepareTestMailTemplate(): void
    {
        $this->createMailTemplateType();
        $this->createMailTemplate();
        $this->createMailTemplateTranslation();
    }

    private function createMailTemplateType(): void
    {
        $mailTemplateType = [
            'id' => $this->mailTemplateTypeByteId,
            'technical_name' => 'TEST TYPE',
            'available_entities' => '[]',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $sql = <<<'SQL'
INSERT INTO mail_template_type (id, technical_name, available_entities, created_at) 
VALUES (:id, :technical_name, :available_entities, :created_at);
SQL;

        $this->connection->executeStatement($sql, $mailTemplateType);
    }

    private function createMailTemplate(): void
    {
        $mailTemplate = [
            'id' => $this->mailTemplateByteId,
            'mail_template_type_id' => $this->mailTemplateTypeByteId,
            'system_default' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $sql = <<<'SQL'
INSERT INTO mail_template (id, mail_template_type_id, system_default, created_at) 
VALUES (:id, :mail_template_type_id, :system_default, :created_at);
SQL;

        $this->connection->executeStatement($sql, $mailTemplate);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getTestTranslation(): array|false
    {
        $sql = <<<'SQL'
SELECT mail_template_id, language_id, sender_name, subject, content_html, content_plain
FROM mail_template_translation
WHERE mail_template_id = :mail_template_id
AND language_id = :language_id
SQL;

        return $this->connection->fetchAssociative(
            $sql,
            [
                'mail_template_id' => $this->mailTemplateByteId,
                'language_id' => $this->languageByteId,
            ]
        );
    }

    private function createMailTemplateTranslation(): void
    {
        $contentHtml = <<<html
<h1>Hello from {{ salesChannel.name }}</h1>
<p>This is another test for replace {{ salesChannel.name }}.</p>
<p>And another one {{ salesChannel.name }}</p>
html;

        $contentPlain = <<<EOD
Hello from {{ salesChannel.name }}

This is another test for replace {{ salesChannel.name }}.

And another one {{ salesChannel.name }}
EOD;

        $mailTemplateTranslation = [
            'mail_template_id' => $this->mailTemplateByteId,
            'language_id' => $this->languageByteId,
            'sender_name' => 'test {{ salesChannel.name }}',
            'subject' => 'test {{ salesChannel.name }}',
            'content_html' => $contentHtml,
            'content_plain' => $contentPlain,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $sql = <<<'SQL'
INSERT INTO mail_template_translation (mail_template_id, language_id, sender_name, subject, content_html, content_plain, created_at) 
VALUES (:mail_template_id, :language_id, :sender_name, :subject, :content_html, :content_plain, :created_at)
SQL;

        $this->connection->executeStatement($sql, $mailTemplateTranslation);
    }

    /**
     * @return List<array<string, mixed>>
     */
    private function getMailTemplateTranslations(): array
    {
        $sql = <<<'SQL'
SELECT
    mtt.mail_template_id,
    mtt.language_id,
    mtt.sender_name,
    mtt.subject,
    mtt.content_html,
    mtt.content_plain
FROM mail_template_translation AS mtt
INNER JOIN mail_template AS mt ON mt.id = mtt.mail_template_id
WHERE mt.system_default = 1
SQL;

        return $this->connection->fetchAllAssociative($sql);
    }
}
