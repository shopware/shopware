<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Checkout\Cart\Order\Event\OrderPlacedEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1556526052MailEvents extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556526052;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateTypes = $connection->executeQuery(
            'SELECT technical_name, id FROM mail_template_type',
            ['technical_name' => NewsletterSubscriptionServiceInterface::MAIL_TYPE_REGISTER]
        )->fetchAll(FetchMode::ASSOCIATIVE);

        $mailTemplateTypeMapping = [];
        foreach ($mailTemplateTypes as $mailTemplateType) {
            $mailTemplateTypeMapping[$mailTemplateType['technical_name']] = $mailTemplateType['id'];
        }

        $orderCofirmationTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $orderCofirmationTemplateId,
                'sender_mail' => 'info@shopware.com',
                'mail_template_type_id' => $mailTemplateTypeMapping[MailTemplateTypes::MAILTYPE_ORDER_CONFIRM],
                'system_default' => 1,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $orderCofirmationTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'subject' => 'Order confirmation',
                'description' => '',
                'content_html' => 'Hello! Your order was successful.',
                'content_plain' => 'Get a real browser please.',
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => OrderPlacedEvent::EVENT_NAME,
                'action_name' => MailSendSubscriber::ACTION_NAME,
                'config' => json_encode([
                    'mail_template_id' => Uuid::fromBytesToHex($orderCofirmationTemplateId),
                ]),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
