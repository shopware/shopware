<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1763377575SendEmailAfterPasswordChangeFlow;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1763377575SendEmailAfterPasswordChangeFlow::class)]
class Migration1763377575SendEmailAfterPasswordChangeFlowTest extends TestCase
{
    use KernelTestBehaviour;

    public function testTimestamp(): void
    {
        $migration = new Migration1763377575SendEmailAfterPasswordChangeFlow();
        static::assertSame(1763377575, $migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1763377575SendEmailAfterPasswordChangeFlow();

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

        $flow = $connection->fetchAllAssociative('SELECT * FROM `flow` WHERE `event_name` = :name', ['name' => CustomerPasswordChangedEvent::EVENT_NAME]);
        static::assertCount(1, $flow);

        $flowSequence = $connection->fetchAllAssociative('SELECT * FROM `flow_sequence` WHERE `flow_id` = :id', ['id' => $flow[0]['id']]);
        static::assertIsArray($flowSequence);
        static::assertCount(1, $flowSequence);
        static::assertArrayHasKey('action_name', $flowSequence[0]);
        static::assertSame(SendMailAction::ACTION_NAME, $flowSequence[0]['action_name']);

        $flowTemplate = $connection->fetchAllAssociative('SELECT * FROM `flow_template` WHERE JSON_EXTRACT(config, \'$.eventName\') = :eventName', [
            'eventName' => CustomerPasswordChangedEvent::EVENT_NAME,
        ]);
        static::assertCount(1, $flowTemplate);
        static::assertSame('Send password change notification to customers', $flowTemplate[0]['name']);
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

        $deletedMailType = $connection->executeStatement(
            'DELETE FROM `mail_template_type` WHERE `technical_name` = :event',
            ['event' => CustomerPasswordChangedEvent::EVENT_NAME]
        );
        static::assertSame(1, $deletedMailType);

        $deletedFlow = $connection->executeStatement(
            'DELETE FROM `flow` WHERE `event_name` = :name',
            ['name' => CustomerPasswordChangedEvent::EVENT_NAME]
        );

        static::assertSame(1, $deletedFlow);
    }
}
