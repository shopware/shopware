<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1789026910CustomerMailGreetingDisplayName;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1789026910CustomerMailGreetingDisplayName::class)]
class Migration1789026910CustomerMailGreetingDisplayNameTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1789026910, (new Migration1789026910CustomerMailGreetingDisplayName())->getCreationTimestamp());
    }

    #[DataProvider('mailTypeProvider')]
    public function testItGreetsWithTheDisplayName(string $type): void
    {
        $migration = new Migration1789026910CustomerMailGreetingDisplayName();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach ($this->contents($type) as $content) {
            static::assertStringContainsString('.displayName', $content);
            static::assertStringNotContainsString('customer.lastName', $content);
            static::assertStringNotContainsString('customer.firstName', $content);
        }
    }

    /**
     * The guard in UpdateMailTrait is the whole reason this migration is safe to ship, so it gets a
     * test of its own rather than being taken on trust.
     */
    public function testAMailTheShopEditedIsLeftAlone(): void
    {
        $type = MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_ACCEPTED;
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
            ['content_plain' => 'Hello {{ customer.lastName }}, we wrote this ourselves.'],
            ['mail_template_id' => $templateId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        $this->connection->update(
            'mail_template',
            ['updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['id' => $templateId]
        );

        (new Migration1789026910CustomerMailGreetingDisplayName())->update($this->connection);

        $content = $this->connection->fetchOne(
            'SELECT content_plain FROM mail_template_translation WHERE mail_template_id = :id AND language_id = :language',
            ['id' => $templateId, 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        static::assertSame('Hello {{ customer.lastName }}, we wrote this ourselves.', $content);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mailTypeProvider(): iterable
    {
        yield 'group registration accepted' => [MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_ACCEPTED];
        yield 'group registration declined' => [MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_DECLINED];
        yield 'customer recovery request' => [MailTemplateTypes::MAILTYPE_CUSTOMER_RECOVERY_REQUEST];
        yield 'customer register double opt in' => [MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER_DOUBLE_OPT_IN];
        yield 'guest order double opt in' => [MailTemplateTypes::MAILTYPE_GUEST_ORDER_DOUBLE_OPT_IN];
        yield 'password change' => [MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE];
        yield 'customer password changed' => [CustomerPasswordChangedEvent::EVENT_NAME];
    }

    /**
     * @return list<string>
     */
    private function contents(string $type): array
    {
        /** @var list<string> $contents */
        $contents = $this->connection->fetchFirstColumn(
            'SELECT mail_template_translation.content_plain
             FROM mail_template_translation
             INNER JOIN mail_template ON mail_template.id = mail_template_translation.mail_template_id
             INNER JOIN mail_template_type ON mail_template.mail_template_type_id = mail_template_type.id
             WHERE mail_template_type.technical_name = :type AND mail_template.system_default = 1',
            ['type' => $type]
        );

        static::assertNotEmpty($contents);

        return $contents;
    }
}
