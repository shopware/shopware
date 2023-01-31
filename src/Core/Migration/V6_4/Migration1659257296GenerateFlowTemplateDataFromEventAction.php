<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Aggregate\FlowTemplate\FlowTemplateDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('business-ops')]
class Migration1659257296GenerateFlowTemplateDataFromEventAction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1659257296;
    }

    public function update(Connection $connection): void
    {
        $existingFlowTemplates = $this->getExistingFlowTemplates($connection);

        $mailTemplates = $this->getDefaultMailTemplates($connection);
        $eventActions = $this->getDefaultEventActions();

        $flowTemplates = [];
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($eventActions as $eventAction) {
            $templateName = $this->getEventFullNameByEventName($eventAction['event_name']);

            if (\in_array($templateName, $existingFlowTemplates, true)) {
                continue;
            }

            $flowTemplate = [
                'id' => Uuid::randomBytes(),
                'name' => $templateName,
                'created_at' => $createdAt,
            ];

            $config = !\array_key_exists($eventAction['mail_template_type'], $mailTemplates) ? null
                : $this->getConfigData($mailTemplates[$eventAction['mail_template_type']]);

            $flowTemplate['config'] = json_encode([
                'eventName' => $eventAction['event_name'],
                'description' => null,
                'customFields' => null,
                'sequences' => [
                    [
                        'id' => Uuid::randomHex(),
                        'actionName' => 'action.mail.send',
                        'config' => $config,
                        'parentId' => null,
                        'ruleId' => null,
                        'position' => 1,
                        'trueCase' => 0,
                        'displayGroup' => 1,
                    ],
                ],
            ], \JSON_THROW_ON_ERROR);

            $flowTemplates[] = $flowTemplate;
        }

        $queue = new MultiInsertQueryQueue($connection);

        foreach ($flowTemplates as $flowTemplate) {
            $queue->addInsert(FlowTemplateDefinition::ENTITY_NAME, $flowTemplate);
        }

        $queue->execute();
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function getDefaultMailTemplates(Connection $connection): array
    {
        $mailTemplates = $connection->fetchAllAssociative('
                SELECT LOWER(HEX(mail_template.id)) as mail_template_id,
                       LOWER(HEX(mail_template.mail_template_type_id)) as mail_template_type_id,
                       mail_template_type.technical_name
                FROM mail_template
                INNER JOIN mail_template_type ON mail_template_type.id = mail_template.mail_template_type_id
                WHERE mail_template.system_default = 1
        ');

        $result = [];
        foreach ($mailTemplates as $mailTemplate) {
            $result[$mailTemplate['technical_name']] = [
                'mail_template_id' => $mailTemplate['mail_template_id'],
                'mail_template_type_id' => $mailTemplate['mail_template_type_id'],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, string> $mailTemplateData
     *
     * @return array<string, mixed>
     */
    private function getConfigData(array $mailTemplateData): array
    {
        $config = [];
        foreach ($mailTemplateData as $key => $value) {
            $key = lcfirst(implode('', array_map('ucfirst', explode('_', $key))));
            $config[$key] = $value;
        }

        $config['recipient'] = ['data' => [], 'type' => 'default'];

        return $config;
    }

    /**
     * @return array<array<string, string>>
     */
    private function getDefaultEventActions(): array
    {
        return [
            [
                'event_name' => 'contact_form.send',
                'mail_template_type' => 'contact_form',
            ],
            [
                'event_name' => 'newsletter.register',
                'mail_template_type' => 'newsletterRegister',
            ],
            [
                'event_name' => 'user.recovery.request',
                'mail_template_type' => 'user.recovery.request',
            ],
            [
                'event_name' => 'checkout.customer.double_opt_in_guest_order',
                'mail_template_type' => 'guest_order.double_opt_in',
            ],
            [
                'event_name' => 'checkout.customer.double_opt_in_registration',
                'mail_template_type' => 'customer_register.double_opt_in',
            ],
            [
                'event_name' => 'checkout.order.placed',
                'mail_template_type' => 'order_confirmation_mail',
            ],
            [
                'event_name' => 'customer.group.registration.declined',
                'mail_template_type' => 'customer.group.registration.declined',
            ],
            [
                'event_name' => 'customer.group.registration.accepted',
                'mail_template_type' => 'customer.group.registration.accepted',
            ],
            [
                'event_name' => 'newsletter.confirm',
                'mail_template_type' => 'newsletterRegister',
            ],
            [
                'event_name' => 'customer.recovery.request',
                'mail_template_type' => 'customer.recovery.request',
            ],
            [
                'event_name' => 'checkout.customer.register',
                'mail_template_type' => 'customer_register',
            ],
            [
                'event_name' => 'state_enter.order_transaction.state.paid_partially',
                'mail_template_type' => 'order_transaction.state.paid_partially',
            ],
            [
                'event_name' => 'state_enter.order_delivery.state.returned',
                'mail_template_type' => 'order_delivery.state.returned',
            ],
            [
                'event_name' => 'state_enter.order_delivery.state.returned_partially',
                'mail_template_type' => 'order_delivery.state.returned_partially',
            ],
            [
                'event_name' => 'state_enter.order_transaction.state.refunded',
                'mail_template_type' => 'order_transaction.state.refunded',
            ],
            [
                'event_name' => 'state_enter.order_transaction.state.paid',
                'mail_template_type' => 'order_transaction.state.paid',
            ],
            [
                'event_name' => 'state_enter.order.state.in_progress',
                'mail_template_type' => 'order.state.in_progress',
            ],
            [
                'event_name' => 'state_enter.order_transaction.state.refunded_partially',
                'mail_template_type' => 'order_transaction.state.refunded_partially',
            ],
            [
                'event_name' => 'state_enter.order_transaction.state.open',
                'mail_template_type' => 'order_transaction.state.open',
            ],
            [
                'event_name' => 'state_enter.order_delivery.state.shipped',
                'mail_template_type' => 'order_delivery.state.shipped',
            ],
            [
                'event_name' => 'state_enter.order_delivery.state.shipped_partially',
                'mail_template_type' => 'order_delivery.state.shipped_partially',
            ],
            [
                'event_name' => 'state_enter.order_delivery.state.cancelled',
                'mail_template_type' => 'order_delivery.state.cancelled',
            ],
            [
                'event_name' => 'state_enter.order_transaction.state.cancelled',
                'mail_template_type' => 'order_transaction.state.cancelled',
            ],
            [
                'event_name' => 'state_enter.order.state.cancelled',
                'mail_template_type' => 'order.state.cancelled',
            ],
            [
                'event_name' => 'state_enter.order_transaction.state.reminded',
                'mail_template_type' => 'order_transaction.state.reminded',
            ],
            [
                'event_name' => 'state_enter.order.state.completed',
                'mail_template_type' => 'order.state.completed',
            ],
        ];
    }

    private function getEventFullNameByEventName(string $eventName): string
    {
        $listEventName = [
            'checkout.customer.before.login' => 'Customer has requested login',
            'checkout.customer.changed-payment-method' => 'Customer changes payment',
            'checkout.customer.deleted' => 'Customer account deleted',
            'checkout.customer.double_opt_in_guest_order' => 'Guest account registered with double opt-in',
            'checkout.customer.double_opt_in_registration' => 'Customer account registered with double opt-in',
            'checkout.customer.guest_register' => 'Guest account registered',
            'checkout.customer.login' => 'Customer logs on',
            'checkout.customer.logout' => 'Customer logs off',
            'checkout.customer.register' => 'Customer account registered',
            'checkout.order.placed' => 'Order placed',
            'contact_form.send' => 'Contact form sent',
            'customer.group.registration.accepted' => 'Customer group registration request accepted',
            'customer.group.registration.declined' => 'Customer group registration request declined',
            'customer.recovery.request' => 'Customer requests new password',
            'mail.after.create.message' => 'Email created',
            'mail.before.send' => 'Email is being sent',
            'mail.sent' => 'Email sent',
            'newsletter.confirm' => 'Newsletter sign-up confirmed',
            'newsletter.register' => 'Newsletter sign-up registered',
            'newsletter.unsubscribe' => 'Newsletter unsubscribed',
            'newsletter.update' => 'Newsletter sign-up updated',
            'product_export.log' => 'Product export executed',
            'state_enter.order.state.cancelled' => 'Order enters status cancelled',
            'state_enter.order.state.completed' => 'Order enters status completed',
            'state_enter.order.state.in_progress' => 'Order enters status in progress',
            'state_enter.order.state.open' => 'Order enters status open',
            'state_enter.order_delivery.state.cancelled' => 'Shipment enters status cancelled',
            'state_enter.order_delivery.state.open' => 'Shipment enters status open',
            'state_enter.order_delivery.state.returned' => 'Shipment enters status returned',
            'state_enter.order_delivery.state.returned_partially' => 'Shipment enters status partially returned',
            'state_enter.order_delivery.state.shipped' => 'Shipment enters status shipped',
            'state_enter.order_delivery.state.shipped_partially' => 'Shipment enters status partially shipped',
            'state_enter.order_transaction.state.authorized' => 'Payment enters status authorised',
            'state_enter.order_transaction.state.cancelled' => 'Payment enters status cancelled',
            'state_enter.order_transaction.state.chargeback' => 'Payment enters status refunded',
            'state_enter.order_transaction.state.failed' => 'Payment enters status failed',
            'state_enter.order_transaction.state.in_progress' => 'Payment enters status in progress',
            'state_enter.order_transaction.state.open' => 'Payment enters status open',
            'state_enter.order_transaction.state.paid' => 'Payment enters status paid',
            'state_enter.order_transaction.state.paid_partially' => 'Payment enters status partially paid',
            'state_enter.order_transaction.state.refunded' => 'Payment enters status refunded',
            'state_enter.order_transaction.state.refunded_partially' => 'Payment enters status partially refunded',
            'state_enter.order_transaction.state.reminded' => 'Payment enters status reminder sent',
            'state_leave.order.state.cancelled' => 'Order leaves status cancelled',
            'state_leave.order.state.completed' => 'Order leaves status completed',
            'state_leave.order.state.in_progress' => 'Order leaves status in progress',
            'state_leave.order.state.open' => 'Order leaves status open',
            'state_leave.order_delivery.state.cancelled' => 'Shipment leaves status cancelled',
            'state_leave.order_delivery.state.open' => 'Shipment leaves status open',
            'state_leave.order_delivery.state.returned' => 'Shipment leaves status returned',
            'state_leave.order_delivery.state.returned_partially' => 'Shipment leaves status partially returned',
            'state_leave.order_delivery.state.shipped' => 'Shipment leaves status shipped',
            'state_leave.order_delivery.state.shipped_partially' => 'Shipment leaves status partially shipped',
            'state_leave.order_transaction.state.authorized' => 'Payment leaves status authorised',
            'state_leave.order_transaction.state.cancelled' => 'Payment leaves status cancelled',
            'state_leave.order_transaction.state.chargeback' => 'Payment leaves status refunded',
            'state_leave.order_transaction.state.failed' => 'Payment leaves status failed',
            'state_leave.order_transaction.state.in_progress' => 'Payment leaves status in progress',
            'state_leave.order_transaction.state.open' => 'Payment leaves status open',
            'state_leave.order_transaction.state.paid' => 'Payment leaves status paid',
            'state_leave.order_transaction.state.paid_partially' => 'Payment leaves status partially paid',
            'state_leave.order_transaction.state.refunded' => 'Payment leaves status refunded',
            'state_leave.order_transaction.state.refunded_partially' => 'Payment leaves status partially refunded',
            'state_leave.order_transaction.state.reminded' => 'Payment leaves status reminder sent',
            'user.recovery.request' => 'User recovery request sent',
        ];

        if (\array_key_exists($eventName, $listEventName)) {
            return $listEventName[$eventName];
        }

        return $eventName;
    }

    /**
     * @return string[]
     */
    private function getExistingFlowTemplates(Connection $connection): array
    {
        /** @var string[] $flowTemplates */
        $flowTemplates = $connection->fetchFirstColumn('SELECT DISTINCT name FROM flow_template');

        return array_unique(array_filter($flowTemplates));
    }
}
