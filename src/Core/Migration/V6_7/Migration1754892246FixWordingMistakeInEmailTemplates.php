<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1754892246FixWordingMistakeInEmailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    private const MAIL_TYPE_DIRS = [
        MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE,
        MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE,
        MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE,
        MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
        MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED,
    ];

    public function getCreationTimestamp(): int
    {
        return 1754892246;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        foreach (self::MAIL_TYPE_DIRS as $mailTypeDirectory) {
            $update = new MailUpdate($mailTypeDirectory);
            $update->setEnPlain($filesystem->readFile(\sprintf('%s/../Fixtures/mails/%s/en-plain.html.twig', __DIR__, $mailTypeDirectory)));
            $update->setEnHtml($filesystem->readFile(\sprintf('%s/../Fixtures/mails/%s/en-html.html.twig', __DIR__, $mailTypeDirectory)));
            $update->setDePlain($filesystem->readFile(\sprintf('%s/../Fixtures/mails/%s/de-plain.html.twig', __DIR__, $mailTypeDirectory)));
            $update->setDeHtml($filesystem->readFile(\sprintf('%s/../Fixtures/mails/%s/de-html.html.twig', __DIR__, $mailTypeDirectory)));
            $this->updateMail($update, $connection);
        }
    }
}
