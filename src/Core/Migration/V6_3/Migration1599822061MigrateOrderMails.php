<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1599822061MigrateOrderMails extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599822061;
    }

    public function update(Connection $connection): void
    {
        // migrate existing event_actions
        $events = $connection->fetchAll(
            'SELECT event_name, config FROM event_action WHERE `action_name` = :action',
            ['action' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION]
        );

        $mapping = [];
        foreach ($events as $event) {
            $config = json_decode($event['config'], true);
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
            ->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY)
            ->execute()
            ->fetchAll();

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
            ->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY)
            ->execute()
            ->fetchAll();

        foreach ($mails as $mail) {
            $key = $mail['mail_template_id'] . '.' . $mail['technical_name'];

            $mapping[$key] = $mail;
        }

        return $mapping;
    }

    private function getTypeIds(Connection $connection, array $names): array
    {
        $ids = $connection->fetchAll(
            'SELECT LOWER(HEX(id)) as id FROM mail_template_type WHERE technical_name IN (:names)',
            ['names' => $names],
            ['names' => Connection::PARAM_STR_ARRAY]
        );

        return array_column($ids, 'id');
    }

    private function insertMails(Connection $connection, array $mails): void
    {
        foreach ($mails as $mail) {
            $id = Uuid::randomBytes();

            $insert = [
                'id' => $id,
                'action_name' => MailSendSubscriber::ACTION_NAME,
                'config' => json_encode([
                    'mail_template_id' => $mail['mail_template_id'],

                    // blue green deployment => mail send subscriber expects in blue state a type id without isset() condition
                    'mail_template_type_id' => Uuid::randomHex(),
                ]),
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

    private function prefix(array $mails, string $prefix): array
    {
        foreach ($mails as &$mail) {
            $mail['technical_name'] = $prefix . $mail['technical_name'];
        }

        return $mails;
    }

    private function map(array $events, array $mails): array
    {
        $exploded = [];
        foreach ($events as $event) {
            $typeId = $event['typeId'];

            $typeMails = array_filter($mails, static function ($mail) use ($typeId) {
                return $mail['mail_template_type_id'] === $typeId;
            });

            foreach ($typeMails as &$mail) {
                $mail['technical_name'] = $event['event_name'];
            }
            unset($mail);

            $exploded = array_filter(array_merge($exploded, $typeMails));
        }

        return $exploded;
    }
}
