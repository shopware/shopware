<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1599822061MigrateOrderMails extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599822061;
    }

    public function update(Connection $connection): void
    {
        // migrate existing event_actions
        $events = $connection->fetchAllAssociative(
            'SELECT event_name, config FROM event_action WHERE `action_name` = :action',
            ['action' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION]
        );

        $mapping = [];
        foreach ($events as $event) {
            $config = json_decode((string) $event['config'], true, 512, \JSON_THROW_ON_ERROR);
            if (!isset($config['mail_template_type_id'])) {
                continue;
            }

            $mapping[] = ['event_name' => $event['event_name'], 'typeId' => $config['mail_template_type_id']];
        }

        $mails = $this->fetchMails($connection, array_column($mapping, 'typeId'));

        $mails = $this->map($mapping, $mails);

        $this->insertMails($connection, $mails);

        // generate entries for order state mails
        $ids = $this->getTypeIds($connection, [
            'order_delivery.state.cancelled',
            'order_delivery.state.returned',
            'order_delivery.state.returned_partially',
            'order_delivery.state.shipped',
            'order_delivery.state.shipped_partially',
            'order_transaction.state.cancelled',
            'order_transaction.state.open',
            'order_transaction.state.paid',
            'order_transaction.state.paid_partially',
            'order_transaction.state.refunded',
            'order_transaction.state.refunded_partially',
            'order_transaction.state.reminded',
            'order.state.cancelled',
            'order.state.completed',
            'order.state.in_progress',
            // order.state.open is removed, this state is handled by 'checkout.order.placed'
        ]);

        // fetch order state mails
        $states = $this->fetchMails($connection, $ids);

        // add prefix for state event
        $states = $this->prefix($states, 'state_enter.');

        $this->insertMails($connection, $states);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, array<string, string>>
     */
    private function fetchMails(Connection $connection, array $ids): array
    {
        $mails = $connection->createQueryBuilder()
            ->select([
                'LOWER(HEX(mail_template.id)) as mail_template_id',
                'NULL as sales_channel_id',
                'mail_template_type.technical_name',
                'LOWER(HEX(mail_template_type.id)) as mail_template_type_id',
            ])
            ->from('mail_template')
            ->innerJoin(
                'mail_template',
                'mail_template_type',
                'mail_template_type',
                'mail_template.mail_template_type_id = mail_template_type.id'
            )
            ->andWhere('mail_template_type.id IN (:ids)')
            ->setParameter('ids', Uuid::fromHexToBytesList($ids), ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchAllAssociative();

        $mapping = [];
        foreach ($mails as $mail) {
            $key = $mail['mail_template_id'] . '.' . $mail['technical_name'];
            $mapping[$key] = $mail;
        }

        $mails = $connection->createQueryBuilder()
            ->select([
                'LOWER(HEX(mail_template_sales_channel.mail_template_id)) as mail_template_id',
                'mail_template_sales_channel.sales_channel_id',
                'mail_template_type.technical_name',
                'LOWER(HEX(mail_template_type.id)) as mail_template_type_id',
            ])
            ->from('mail_template_sales_channel')
            ->innerJoin(
                'mail_template_sales_channel',
                'mail_template_type',
                'mail_template_type',
                'mail_template_sales_channel.mail_template_type_id = mail_template_type.id'
            )
            ->andWhere('mail_template_type.id IN (:ids)')
            ->setParameter('ids', Uuid::fromHexToBytesList($ids), ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($mails as $mail) {
            $key = $mail['mail_template_id'] . '.' . $mail['technical_name'];

            $mapping[$key] = $mail;
        }

        return $mapping;
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function getTypeIds(Connection $connection, array $names): array
    {
        return $connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) as id FROM mail_template_type WHERE technical_name IN (:names)',
            ['names' => $names],
            ['names' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param array<string, array<string, string>> $mails
     */
    private function insertMails(Connection $connection, array $mails): void
    {
        foreach ($mails as $mail) {
            $id = Uuid::randomBytes();

            $insert = [
                'id' => $id,
                'action_name' => MailSendSubscriberConfig::ACTION_NAME,
                'config' => json_encode([
                    'mail_template_id' => $mail['mail_template_id'],

                    // blue green deployment => mail send subscriber expects in blue state a type id without isset() condition
                    'mail_template_type_id' => Uuid::randomHex(),
                ], \JSON_THROW_ON_ERROR),
                'event_name' => $mail['technical_name'],
                // will be set to true, after feature flag removed
                'active' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => null,
            ];

            $connection->insert('event_action', $insert);

            if (isset($mail['sales_channel_id'])) {
                $connection->insert('event_action_sales_channel', [
                    'event_action_id' => $id,
                    'sales_channel_id' => $mail['sales_channel_id'],
                ]);
            }
        }
    }

    /**
     * @param array<string, array<string, string>> $mails
     *
     * @return array<string, array<string, string>>
     */
    private function prefix(array $mails, string $prefix): array
    {
        foreach ($mails as &$mail) {
            $mail['technical_name'] = $prefix . $mail['technical_name'];
        }

        return $mails;
    }

    /**
     * @param list<array<string, string>> $events
     * @param array<string, array<string, string>> $mails
     *
     * @return array<string, array<string, string>>
     */
    private function map(array $events, array $mails): array
    {
        $exploded = [];
        foreach ($events as $event) {
            $typeId = $event['typeId'];

            $typeMails = array_filter($mails, static fn ($mail) => $mail['mail_template_type_id'] === $typeId);

            foreach ($typeMails as &$mail) {
                $mail['technical_name'] = $event['event_name'];
            }
            unset($mail);

            $exploded = [...$exploded, ...$typeMails];
        }

        return $exploded;
    }
}
