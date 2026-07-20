<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;
use Symfony\Component\Filesystem\Filesystem;

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
        $filesystem = new Filesystem();

        $orderDeliveryStateShippedPartiallyEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/en-html.html.twig');
        $orderDeliveryStateShippedPartiallyEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/en-plain.html.twig');
        $orderDeliveryStateShippedPartiallyDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/de-html.html.twig');
        $orderDeliveryStateShippedPartiallyDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/de-plain.html.twig');

        $orderTransactionStateRefundedPartiallyEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/en-html.html.twig');
        $orderTransactionStateRefundedPartiallyEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/en-plain.html.twig');
        $orderTransactionStateRefundedPartiallyDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/de-html.html.twig');
        $orderTransactionStateRefundedPartiallyDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/de-plain.html.twig');

        $orderTransactionStateRemindedEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/en-html.html.twig');
        $orderTransactionStateRemindedEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/en-plain.html.twig');
        $orderTransactionStateRemindedDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/de-html.html.twig');
        $orderTransactionStateRemindedDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/de-plain.html.twig');

        $orderTransactionStateOpenEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/en-html.html.twig');
        $orderTransactionStateOpenEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/en-plain.html.twig');
        $orderTransactionStateOpenDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/de-html.html.twig');
        $orderTransactionStateOpenDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/de-plain.html.twig');

        $orderDeliveryStateReturnedPartiallyEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/en-html.html.twig');
        $orderDeliveryStateReturnedPartiallyEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/en-plain.html.twig');
        $orderDeliveryStateReturnedPartiallyDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/de-html.html.twig');
        $orderDeliveryStateReturnedPartiallyDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/de-plain.html.twig');

        $orderTransactionStatePaidEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig');
        $orderTransactionStatePaidEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig');
        $orderTransactionStatePaidDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-html.html.twig');
        $orderTransactionStatePaidDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-plain.html.twig');

        $orderDeliveryStateReturnedEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/en-html.html.twig');
        $orderDeliveryStateReturnedEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/en-plain.html.twig');
        $orderDeliveryStateReturnedDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/de-html.html.twig');
        $orderDeliveryStateReturnedDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/de-plain.html.twig');

        $orderStateCancelledEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/en-html.html.twig');
        $orderStateCancelledEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/en-plain.html.twig');
        $orderStateCancelledDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/de-html.html.twig');
        $orderStateCancelledDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/de-plain.html.twig');

        $orderDeliveryStateCancelledEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/en-html.html.twig');
        $orderDeliveryStateCancelledEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/en-plain.html.twig');
        $orderDeliveryStateCancelledDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/de-html.html.twig');
        $orderDeliveryStateCancelledDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/de-plain.html.twig');

        $orderDeliveryStateShippedEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/en-html.html.twig');
        $orderDeliveryStateShippedEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/en-plain.html.twig');
        $orderDeliveryStateShippedDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/de-html.html.twig');
        $orderDeliveryStateShippedDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/de-plain.html.twig');

        $orderTransactionStateCancelledEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig');
        $orderTransactionStateCancelledEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig');
        $orderTransactionStateCancelledDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig');
        $orderTransactionStateCancelledDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig');

        $orderTransactionStateRefundedEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/en-html.html.twig');
        $orderTransactionStateRefundedEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/en-plain.html.twig');
        $orderTransactionStateRefundedDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/de-html.html.twig');
        $orderTransactionStateRefundedDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/de-plain.html.twig');

        $orderTransactionStatePaidPartiallyEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/en-html.html.twig');
        $orderTransactionStatePaidPartiallyEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/en-plain.html.twig');
        $orderTransactionStatePaidPartiallyDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/de-html.html.twig');
        $orderTransactionStatePaidPartiallyDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/de-plain.html.twig');

        $orderTransactionStateAuthorizedEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-html.html.twig');
        $orderTransactionStateAuthorizedEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-plain.html.twig');
        $orderTransactionStateAuthorizedDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-html.html.twig');
        $orderTransactionStateAuthorizedDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-plain.html.twig');

        $orderTransactionStateChargebackEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-html.html.twig');
        $orderTransactionStateChargebackEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-plain.html.twig');
        $orderTransactionStateChargebackDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-html.html.twig');
        $orderTransactionStateChargebackDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-plain.html.twig');

        $orderTransactionStateUnconfirmedEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-html.html.twig');
        $orderTransactionStateUnconfirmedEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-plain.html.twig');
        $orderTransactionStateUnconfirmedDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-html.html.twig');
        $orderTransactionStateUnconfirmedDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-plain.html.twig');

        $orderStateOpenEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.open/en-html.html.twig');
        $orderStateOpenEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.open/en-plain.html.twig');
        $orderStateOpenDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.open/de-html.html.twig');
        $orderStateOpenDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.open/de-plain.html.twig');

        $orderStateInProgressEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.in_progress/en-html.html.twig');
        $orderStateInProgressEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.in_progress/en-plain.html.twig');
        $orderStateInProgressDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.in_progress/de-html.html.twig');
        $orderStateInProgressDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.in_progress/de-plain.html.twig');

        $orderStateCompletedEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.completed/en-html.html.twig');
        $orderStateCompletedEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.completed/en-plain.html.twig');
        $orderStateCompletedDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.completed/de-html.html.twig');
        $orderStateCompletedDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.completed/de-plain.html.twig');

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
