<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1737105721MigrateOrderStateChangeDocumentToA11Y extends MigrationStep
{
    use ImportTranslationsTrait;

    private const LOCALE_EN_GB = 'en-GB';
    private const LOCALE_DE_DE = 'de-DE';

    public function getCreationTimestamp(): int
    {
        return 1737105721;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $documentTypeTranslationMapping = [
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
        ];

        $templateMapping = $this->getTemplateMapping();

        foreach ($documentTypeTranslationMapping as $technicalName) {
            $mailTemplateId = $connection->fetchOne('
                SELECT `mail_template`.`id`
                FROM `mail_template`
                INNER JOIN `mail_template_type`
                    ON `mail_template`.`mail_template_type_id` = `mail_template_type`.`id`
                    AND `mail_template_type`.`technical_name` = :technicalName
                WHERE `mail_template`.`updated_at` IS NULL
           ', ['technicalName' => $technicalName]);

            if (!$mailTemplateId) {
                continue;
            }

            $translations = new Translations(
                [
                    'mail_template_id' => $mailTemplateId,
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => 'Neues Dokument für Ihre Bestellung',
                    'content_html' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_DE_DE, true),
                    'content_plain' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_DE_DE, false),
                ],
                [
                    'mail_template_id' => $mailTemplateId,
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => 'New document for your order',
                    'content_html' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_EN_GB, true),
                    'content_plain' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_EN_GB, false),
                ],
            );

            $this->importTranslation('mail_template_translation', $translations, $connection);
        }
    }

    /**
     * @return array<string, array<string, array<string, string|false>>>
     */
    private function getTemplateMapping(): array
    {
        $orderDeliveryStateShippedPartiallyEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/en-html.html.twig');
        $orderDeliveryStateShippedPartiallyEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/en-plain.html.twig');
        $orderDeliveryStateShippedPartiallyDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/de-html.html.twig');
        $orderDeliveryStateShippedPartiallyDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/de-plain.html.twig');

        $orderTransactionStateRefundedPartiallyEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/en-html.html.twig');
        $orderTransactionStateRefundedPartiallyEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/en-plain.html.twig');
        $orderTransactionStateRefundedPartiallyDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/de-html.html.twig');
        $orderTransactionStateRefundedPartiallyDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/de-plain.html.twig');

        $orderTransactionStateRemindedEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/en-html.html.twig');
        $orderTransactionStateRemindedEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/en-plain.html.twig');
        $orderTransactionStateRemindedDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/de-html.html.twig');
        $orderTransactionStateRemindedDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/de-plain.html.twig');

        $orderTransactionStateOpenEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/en-html.html.twig');
        $orderTransactionStateOpenEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/en-plain.html.twig');
        $orderTransactionStateOpenDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/de-html.html.twig');
        $orderTransactionStateOpenDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/de-plain.html.twig');

        $orderDeliveryStateReturnedPartiallyEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/en-html.html.twig');
        $orderDeliveryStateReturnedPartiallyEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/en-plain.html.twig');
        $orderDeliveryStateReturnedPartiallyDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/de-html.html.twig');
        $orderDeliveryStateReturnedPartiallyDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/de-plain.html.twig');

        $orderTransactionStatePaidEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig');
        $orderTransactionStatePaidEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig');
        $orderTransactionStatePaidDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-html.html.twig');
        $orderTransactionStatePaidDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-plain.html.twig');

        $orderDeliveryStateReturnedEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/en-html.html.twig');
        $orderDeliveryStateReturnedEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/en-plain.html.twig');
        $orderDeliveryStateReturnedDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/de-html.html.twig');
        $orderDeliveryStateReturnedDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/de-plain.html.twig');

        $orderStateCancelledEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.cancelled/en-html.html.twig');
        $orderStateCancelledEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.cancelled/en-plain.html.twig');
        $orderStateCancelledDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.cancelled/de-html.html.twig');
        $orderStateCancelledDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.cancelled/de-plain.html.twig');

        $orderDeliveryStateCancelledEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/en-html.html.twig');
        $orderDeliveryStateCancelledEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/en-plain.html.twig');
        $orderDeliveryStateCancelledDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/de-html.html.twig');
        $orderDeliveryStateCancelledDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/de-plain.html.twig');

        $orderDeliveryStateShippedEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/en-html.html.twig');
        $orderDeliveryStateShippedEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/en-plain.html.twig');
        $orderDeliveryStateShippedDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/de-html.html.twig');
        $orderDeliveryStateShippedDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/de-plain.html.twig');

        $orderTransactionStateCancelledEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig');
        $orderTransactionStateCancelledEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig');
        $orderTransactionStateCancelledDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig');
        $orderTransactionStateCancelledDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig');

        $orderTransactionStateRefundedEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/en-html.html.twig');
        $orderTransactionStateRefundedEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/en-plain.html.twig');
        $orderTransactionStateRefundedDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/de-html.html.twig');
        $orderTransactionStateRefundedDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/de-plain.html.twig');

        $orderTransactionStatePaidPartiallyEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/en-html.html.twig');
        $orderTransactionStatePaidPartiallyEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/en-plain.html.twig');
        $orderTransactionStatePaidPartiallyDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/de-html.html.twig');
        $orderTransactionStatePaidPartiallyDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/de-plain.html.twig');

        $orderTransactionStateAuthorizedEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-html.html.twig');
        $orderTransactionStateAuthorizedEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-plain.html.twig');
        $orderTransactionStateAuthorizedDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-html.html.twig');
        $orderTransactionStateAuthorizedDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-plain.html.twig');

        $orderTransactionStateChargebackEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-html.html.twig');
        $orderTransactionStateChargebackEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-plain.html.twig');
        $orderTransactionStateChargebackDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-html.html.twig');
        $orderTransactionStateChargebackDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-plain.html.twig');

        $orderTransactionStateUnconfirmedEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-html.html.twig');
        $orderTransactionStateUnconfirmedEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-plain.html.twig');
        $orderTransactionStateUnconfirmedDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-html.html.twig');
        $orderTransactionStateUnconfirmedDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-plain.html.twig');

        $orderStateOpenEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.open/en-html.html.twig');
        $orderStateOpenEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.open/en-plain.html.twig');
        $orderStateOpenDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.open/de-html.html.twig');
        $orderStateOpenDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.open/de-plain.html.twig');

        $orderStateInProgressEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.in_progress/en-html.html.twig');
        $orderStateInProgressEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.in_progress/en-plain.html.twig');
        $orderStateInProgressDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.in_progress/de-html.html.twig');
        $orderStateInProgressDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.in_progress/de-plain.html.twig');

        $orderStateCompletedEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.completed/en-html.html.twig');
        $orderStateCompletedEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.completed/en-plain.html.twig');
        $orderStateCompletedDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.completed/de-html.html.twig');
        $orderStateCompletedDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/order.state.completed/de-plain.html.twig');

        return [
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY => [
                self::LOCALE_EN_GB => [
                    'html' => $orderDeliveryStateShippedPartiallyEnHtml,
                    'plain' => $orderDeliveryStateShippedPartiallyEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderDeliveryStateShippedPartiallyDeHtml,
                    'plain' => $orderDeliveryStateShippedPartiallyDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStateRefundedPartiallyEnHtml,
                    'plain' => $orderTransactionStateRefundedPartiallyEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStateRefundedPartiallyDeHtml,
                    'plain' => $orderTransactionStateRefundedPartiallyDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStateRemindedEnHtml,
                    'plain' => $orderTransactionStateRemindedEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStateRemindedDeHtml,
                    'plain' => $orderTransactionStateRemindedDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStateOpenEnHtml,
                    'plain' => $orderTransactionStateOpenEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStateOpenDeHtml,
                    'plain' => $orderTransactionStateOpenDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY => [
                self::LOCALE_EN_GB => [
                    'html' => $orderDeliveryStateReturnedPartiallyEnHtml,
                    'plain' => $orderDeliveryStateReturnedPartiallyEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderDeliveryStateReturnedPartiallyDeHtml,
                    'plain' => $orderDeliveryStateReturnedPartiallyDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStatePaidEnHtml,
                    'plain' => $orderTransactionStatePaidEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStatePaidDeHtml,
                    'plain' => $orderTransactionStatePaidDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderDeliveryStateReturnedEnHtml,
                    'plain' => $orderDeliveryStateReturnedEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderDeliveryStateReturnedDeHtml,
                    'plain' => $orderDeliveryStateReturnedDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderStateCancelledEnHtml,
                    'plain' => $orderStateCancelledEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderStateCancelledDeHtml,
                    'plain' => $orderStateCancelledDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderDeliveryStateCancelledEnHtml,
                    'plain' => $orderDeliveryStateCancelledEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderDeliveryStateCancelledDeHtml,
                    'plain' => $orderDeliveryStateCancelledDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderDeliveryStateShippedEnHtml,
                    'plain' => $orderDeliveryStateShippedEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderDeliveryStateShippedDeHtml,
                    'plain' => $orderDeliveryStateShippedDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStateCancelledEnHtml,
                    'plain' => $orderTransactionStateCancelledEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStateCancelledDeHtml,
                    'plain' => $orderTransactionStateCancelledDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStateRefundedEnHtml,
                    'plain' => $orderTransactionStateRefundedEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStateRefundedDeHtml,
                    'plain' => $orderTransactionStateRefundedDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStatePaidPartiallyEnHtml,
                    'plain' => $orderTransactionStatePaidPartiallyEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStatePaidPartiallyDeHtml,
                    'plain' => $orderTransactionStatePaidPartiallyDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStateAuthorizedEnHtml,
                    'plain' => $orderTransactionStateAuthorizedEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStateAuthorizedDeHtml,
                    'plain' => $orderTransactionStateAuthorizedDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStateChargebackEnHtml,
                    'plain' => $orderTransactionStateChargebackEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStateChargebackDeHtml,
                    'plain' => $orderTransactionStateChargebackDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderTransactionStateUnconfirmedEnHtml,
                    'plain' => $orderTransactionStateUnconfirmedEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderTransactionStateUnconfirmedDeHtml,
                    'plain' => $orderTransactionStateUnconfirmedDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN => [
                self::LOCALE_EN_GB => [
                    'html' => $orderStateOpenEnHtml,
                    'plain' => $orderStateOpenEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderStateOpenDeHtml,
                    'plain' => $orderStateOpenDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS => [
                self::LOCALE_EN_GB => [
                    'html' => $orderStateInProgressEnHtml,
                    'plain' => $orderStateInProgressEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderStateInProgressDeHtml,
                    'plain' => $orderStateInProgressDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED => [
                self::LOCALE_EN_GB => [
                    'html' => $orderStateCompletedEnHtml,
                    'plain' => $orderStateCompletedEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $orderStateCompletedDeHtml,
                    'plain' => $orderStateCompletedDePlain,
                ],
            ],
        ];
    }

    /**
     * @param array<string, array<string, array<string, string|false>>> $templateMapping
     */
    private function getMailTemplateContent(array $templateMapping, string $technicalName, string $locale, bool $html): string
    {
        if (!\is_string($templateMapping[$technicalName][$locale][$html ? 'html' : 'plain'])) {
            return '';
        }

        return $templateMapping[$technicalName][$locale][$html ? 'html' : 'plain'];
    }
}
