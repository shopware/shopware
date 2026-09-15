<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1789397400OrderMailGreetingDisplayName;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1789397400OrderMailGreetingDisplayName::class)]
class Migration1789397400OrderMailGreetingDisplayNameTest extends TestCase
{
    use MigrationTestTrait;

    private const LOCALES = ['en-GB', 'de-DE'];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1789397400, (new Migration1789397400OrderMailGreetingDisplayName())->getCreationTimestamp());
    }

    #[DataProvider('mailTypeProvider')]
    public function testItGreetsWithTheDisplayName(string $type): void
    {
        $this->seedShippedGreeting($type);

        $migration = new Migration1789397400OrderMailGreetingDisplayName();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach ($this->contents($type) as $content) {
            static::assertStringContainsString('order.orderCustomer.displayName', $content);
            static::assertStringNotContainsString('order.orderCustomer.firstName }} {{ order.orderCustomer.lastName', $content);
        }
    }

    public function testAMailTheShopEditedIsLeftAlone(): void
    {
        $type = MailTemplateTypes::MAILTYPE_ORDER_CONFIRM;
        $templateId = $this->connection->fetchOne(
            'SELECT mail_template.id
             FROM mail_template
             INNER JOIN mail_template_type ON mail_template.mail_template_type_id = mail_template_type.id
             WHERE mail_template_type.technical_name = :type AND mail_template.system_default = 1',
            ['type' => $type]
        );

        static::assertIsString($templateId);

        $this->connection->update(
            'mail_template_translation',
            ['content_plain' => 'Hello {{ order.orderCustomer.lastName }}, we wrote this ourselves.'],
            ['mail_template_id' => $templateId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        $this->connection->update(
            'mail_template',
            ['updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['id' => $templateId]
        );

        (new Migration1789397400OrderMailGreetingDisplayName())->update($this->connection);

        $content = $this->connection->fetchOne(
            'SELECT content_plain FROM mail_template_translation WHERE mail_template_id = :id AND language_id = :language',
            ['id' => $templateId, 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        static::assertSame('Hello {{ order.orderCustomer.lastName }}, we wrote this ourselves.', $content);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mailTypeProvider(): iterable
    {
        foreach (Migration1789397400OrderMailGreetingDisplayName::MAIL_TYPES as $type) {
            yield $type => [$type];
        }
    }

    private function seedShippedGreeting(string $type): void
    {
        $this->connection->executeStatement(
            'UPDATE mail_template_translation
             INNER JOIN mail_template ON mail_template.id = mail_template_translation.mail_template_id
             INNER JOIN mail_template_type ON mail_template.mail_template_type_id = mail_template_type.id
             INNER JOIN language ON language.id = mail_template_translation.language_id
             INNER JOIN locale ON locale.id = language.locale_id
             SET mail_template_translation.content_plain = :plain,
                 mail_template_translation.content_html = :html,
                 mail_template_translation.updated_at = NULL,
                 mail_template.updated_at = NULL
             WHERE mail_template_type.technical_name = :type
             AND mail_template.system_default = 1
             AND (language.id = :system OR locale.code IN (:locales))',
            [
                'plain' => 'Hello {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }}',
                'html' => '<p>Hello {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }}</p>',
                'type' => $type,
                'system' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'locales' => self::LOCALES,
            ],
            ['locales' => ArrayParameterType::STRING]
        );
    }

    /**
     * @return list<string>
     */
    private function contents(string $type): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT mail_template_translation.content_plain, mail_template_translation.content_html
             FROM mail_template_translation
             INNER JOIN mail_template ON mail_template.id = mail_template_translation.mail_template_id
             INNER JOIN mail_template_type ON mail_template.mail_template_type_id = mail_template_type.id
             INNER JOIN language ON language.id = mail_template_translation.language_id
             INNER JOIN locale ON locale.id = language.locale_id
             WHERE mail_template_type.technical_name = :type
             AND mail_template.system_default = 1
             AND (language.id = :system OR locale.code IN (:locales))',
            [
                'type' => $type,
                'system' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'locales' => self::LOCALES,
            ],
            ['locales' => ArrayParameterType::STRING]
        );

        static::assertNotEmpty($rows);

        $contents = [];
        foreach ($rows as $row) {
            $contents[] = (string) $row['content_plain'];
            $contents[] = (string) $row['content_html'];
        }

        return $contents;
    }
}
