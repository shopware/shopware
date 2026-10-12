<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1763377570CreatePasswordChangeMailTemplate;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1763377570CreatePasswordChangeMailTemplate::class)]
class Migration1763377570CreatePasswordChangeMailTemplateTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763377570, (new Migration1763377570CreatePasswordChangeMailTemplate())->getCreationTimestamp());
    }

    public function testTimestamp(): void
    {
        $migration = new Migration1763377570CreatePasswordChangeMailTemplate();
        static::assertSame(1763377570, $migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1763377570CreatePasswordChangeMailTemplate();

        $this->rollback($connection);
        $migration->update($connection);
        $migration->update($connection);

        $mailTemplateType = $connection->fetchAllAssociative('SELECT * FROM `mail_template_type` WHERE `technical_name` = :event', ['event' => CustomerPasswordChangedEvent::EVENT_NAME]);
        static::assertIsArray($mailTemplateType);
        static::assertCount(1, $mailTemplateType);
        static::assertArrayHasKey('technical_name', $mailTemplateType[0]);
        static::assertArrayHasKey('available_entities', $mailTemplateType[0]);
        static::assertSame(CustomerPasswordChangedEvent::EVENT_NAME, $mailTemplateType[0]['technical_name']);
        static::assertSame('{"customer":"customer"}', $mailTemplateType[0]['available_entities']);

        $mailTemplate = $connection->fetchAllAssociative('SELECT * FROM `mail_template` WHERE `mail_template_type_id` = :template', ['template' => $mailTemplateType[0]['id']]);
        static::assertCount(1, $mailTemplate);

        $germanLanguageId = $connection->fetchOne(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `locale`.`id` = `language`.`translation_code_id` WHERE `locale`.`code` = :iso',
            ['iso' => 'de-DE']
        );
        static::assertIsString($germanLanguageId);

        static::assertSame('Kunden-Passwort geändert', $connection->fetchOne(
            'SELECT `name` FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :typeId AND `language_id` = :languageId',
            ['typeId' => $mailTemplateType[0]['id'], 'languageId' => $germanLanguageId]
        ));
        static::assertSame('Kunden-Passwort geändert', $connection->fetchOne(
            'SELECT `subject` FROM `mail_template_translation` WHERE `mail_template_id` = :templateId AND `language_id` = :languageId',
            ['templateId' => $mailTemplate[0]['id'], 'languageId' => $germanLanguageId]
        ));
    }

    private function rollback(Connection $connection): void
    {
        $mailTemplateTypeId = $connection->fetchOne('SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :event', ['event' => CustomerPasswordChangedEvent::EVENT_NAME]);

        $mailTemplateId = $connection->fetchOne('SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :id', ['id' => $mailTemplateTypeId]);

        $deleteMailTranslation = $connection->executeStatement(
            'DELETE FROM `mail_template_translation` WHERE `mail_template_id` = :id',
            ['id' => $mailTemplateId]
        );
        static::assertSame(2, $deleteMailTranslation);

        $deletedMailTemplate = $connection->executeStatement(
            'DELETE FROM `mail_template` WHERE `id` = :id',
            ['id' => $mailTemplateId]
        );
        static::assertSame(1, $deletedMailTemplate);

        $deletedMailType = $connection->executeStatement(
            'DELETE FROM `mail_template_type` WHERE `technical_name` = :event',
            ['event' => CustomerPasswordChangedEvent::EVENT_NAME]
        );
        static::assertSame(1, $deletedMailType);
    }
}
