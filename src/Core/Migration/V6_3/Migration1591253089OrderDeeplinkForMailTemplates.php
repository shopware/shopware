<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1591253089OrderDeeplinkForMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591253089;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        $deLangId = $this->fetchLanguageId('de-DE', $connection);

        $mailTemplateContent = require __DIR__ . '/../Fixtures/MailTemplateContent.php';

        // update order confirmation email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['OrderConfirmation']['en-GB']['html'],
            $mailTemplateContent['OrderConfirmation']['en-GB']['plain'],
            $mailTemplateContent['OrderConfirmation']['de-DE']['html'],
            $mailTemplateContent['OrderConfirmation']['de-DE']['plain']
        );

        // update delivery email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['DeliveryCancellation']['en-GB']['html'],
            $mailTemplateContent['DeliveryCancellation']['en-GB']['plain'],
            $mailTemplateContent['DeliveryCancellation']['de-DE']['html'],
            $mailTemplateContent['DeliveryCancellation']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['DeliveryReturned']['en-GB']['html'],
            $mailTemplateContent['DeliveryReturned']['en-GB']['plain'],
            $mailTemplateContent['DeliveryReturned']['de-DE']['html'],
            $mailTemplateContent['DeliveryReturned']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['DeliveryShippedPartially']['en-GB']['html'],
            $mailTemplateContent['DeliveryShippedPartially']['en-GB']['plain'],
            $mailTemplateContent['DeliveryShippedPartially']['de-DE']['html'],
            $mailTemplateContent['DeliveryShippedPartially']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['DeliveryShipped']['en-GB']['html'],
            $mailTemplateContent['DeliveryShipped']['en-GB']['plain'],
            $mailTemplateContent['DeliveryShipped']['de-DE']['html'],
            $mailTemplateContent['DeliveryShipped']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['DeliveryReturnedPartially']['en-GB']['html'],
            $mailTemplateContent['DeliveryReturnedPartially']['en-GB']['plain'],
            $mailTemplateContent['DeliveryReturnedPartially']['de-DE']['html'],
            $mailTemplateContent['DeliveryReturnedPartially']['de-DE']['plain']
        );

        // update order state email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['OrderCancelled']['en-GB']['html'],
            $mailTemplateContent['OrderCancelled']['en-GB']['plain'],
            $mailTemplateContent['OrderCancelled']['de-DE']['html'],
            $mailTemplateContent['OrderCancelled']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['OrderOpen']['en-GB']['html'],
            $mailTemplateContent['OrderOpen']['en-GB']['plain'],
            $mailTemplateContent['OrderOpen']['de-DE']['html'],
            $mailTemplateContent['OrderOpen']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['OrderInProgress']['en-GB']['html'],
            $mailTemplateContent['OrderInProgress']['en-GB']['plain'],
            $mailTemplateContent['OrderInProgress']['de-DE']['html'],
            $mailTemplateContent['OrderInProgress']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['OrderCompleted']['en-GB']['html'],
            $mailTemplateContent['OrderCompleted']['en-GB']['plain'],
            $mailTemplateContent['OrderCompleted']['de-DE']['html'],
            $mailTemplateContent['OrderCompleted']['de-DE']['plain']
        );

        // update payment email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['PaymentRefundedPartially']['en-GB']['html'],
            $mailTemplateContent['PaymentRefundedPartially']['en-GB']['plain'],
            $mailTemplateContent['PaymentRefundedPartially']['de-DE']['html'],
            $mailTemplateContent['PaymentRefundedPartially']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['PaymentReminded']['en-GB']['html'],
            $mailTemplateContent['PaymentReminded']['en-GB']['plain'],
            $mailTemplateContent['PaymentReminded']['de-DE']['html'],
            $mailTemplateContent['PaymentReminded']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['PaymentOpen']['en-GB']['html'],
            $mailTemplateContent['PaymentOpen']['en-GB']['plain'],
            $mailTemplateContent['PaymentOpen']['de-DE']['html'],
            $mailTemplateContent['PaymentOpen']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['PaymentPaid']['en-GB']['html'],
            $mailTemplateContent['PaymentPaid']['en-GB']['plain'],
            $mailTemplateContent['PaymentPaid']['de-DE']['html'],
            $mailTemplateContent['PaymentPaid']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['PaymentCancelled']['en-GB']['html'],
            $mailTemplateContent['PaymentCancelled']['en-GB']['plain'],
            $mailTemplateContent['PaymentCancelled']['de-DE']['html'],
            $mailTemplateContent['PaymentCancelled']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['PaymentRefunded']['en-GB']['html'],
            $mailTemplateContent['PaymentRefunded']['en-GB']['plain'],
            $mailTemplateContent['PaymentRefunded']['de-DE']['html'],
            $mailTemplateContent['PaymentRefunded']['de-DE']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $connection,
            $enLangId,
            $deLangId,
            $mailTemplateContent['PaymentPaidPartially']['en-GB']['html'],
            $mailTemplateContent['PaymentPaidPartially']['en-GB']['plain'],
            $mailTemplateContent['PaymentPaidPartially']['de-DE']['html'],
            $mailTemplateContent['PaymentPaidPartially']['de-DE']['plain']
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        /** @var string|null $langId */
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId) {
            return null;
        }

        return $langId;
    }

    private function updateMailTemplate(
        string $mailTemplateType,
        Connection $connection,
        ?string $enLangId,
        ?string $deLangId,
        string $getHtmlTemplateEn,
        string $getPlainTemplateEn,
        string $getHtmlTemplateDe,
        string $getPlainTemplateDe
    ): void {
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);

        if ($templateId !== null) {
            // updating available entities of mail template
            $availableEntities = $this->fetchSystemMailTemplateAvailableEntitiesFromType($connection, $mailTemplateType);
            if (!isset($availableEntities['editOrderUrl'])) {
                $availableEntities['editOrderUrl'] = null;
                $sqlStatement = 'UPDATE `mail_template_type` SET `available_entities` = :availableEntities WHERE `technical_name` = :mailTemplateType AND `updated_at` IS NULL';
                $connection->executeStatement($sqlStatement, ['availableEntities' => json_encode($availableEntities, \JSON_THROW_ON_ERROR), 'mailTemplateType' => $mailTemplateType]);
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $getHtmlTemplateEn,
                $getPlainTemplateEn
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $getHtmlTemplateDe,
                $getPlainTemplateDe
            );
        }
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSystemMailTemplateAvailableEntitiesFromType(Connection $connection, string $mailTemplateType): array
    {
        $availableEntities = $connection->executeQuery(
            'SELECT `available_entities` FROM `mail_template_type` WHERE `technical_name` = :mailTemplateType AND updated_at IS NULL;',
            ['mailTemplateType' => $mailTemplateType]
        )->fetchOne();

        if ($availableEntities === false || !\is_string($availableEntities) || json_decode($availableEntities, true, 512, \JSON_THROW_ON_ERROR) === null) {
            return [];
        }

        return json_decode($availableEntities, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateId,
        ?string $langId,
        ?string $contentHtml,
        ?string $contentPlain,
        ?string $senderName = null
    ): void {
        if (!$langId) {
            return;
        }

        $sqlString = '';
        $sqlParams = [
            'templateId' => $mailTemplateId,
            'langId' => $langId,
        ];

        if ($contentHtml !== null) {
            $sqlString .= '`content_html` = :contentHtml ';
            $sqlParams['contentHtml'] = $contentHtml;
        }

        if ($contentPlain !== null) {
            $sqlString .= ($sqlString !== '' ? ', ' : '') . '`content_plain` = :contentPlain ';
            $sqlParams['contentPlain'] = $contentPlain;
        }

        if ($senderName !== null) {
            $sqlString .= ($sqlString !== '' ? ', ' : '') . '`sender_name` = :senderName ';
            $sqlParams['senderName'] = $senderName;
        }

        $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $connection->executeStatement($sqlString, $sqlParams);
    }
}
