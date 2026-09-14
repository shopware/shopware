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
#[Package('checkout')]
class Migration1789397400OrderMailGreetingDisplayName extends MigrationStep
{
    use UpdateMailTrait;

    /**
     * Every shipped order mail greets by the name of the order customer, which is empty on an order of
     * a commercial account without a contact person. The fixture directory of each type is named
     * after the type.
     */
    final public const MAIL_TYPES = [
        MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
        MailTemplateTypes::MAILTYPE_ORDER_PAYMENT_METHOD_CHANGED,
        MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE,
        MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE,
        MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE,
        MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE,
        MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
    ];

    public function getCreationTimestamp(): int
    {
        return 1789397400;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        foreach (self::MAIL_TYPES as $type) {
            $directory = \sprintf('%s/../Fixtures/mails/%s', __DIR__, $type);

            $update = new MailUpdate($type);
            $update->setEnPlain($filesystem->readFile($this->plainFile($filesystem, $directory, 'en')));
            $update->setEnHtml($filesystem->readFile($directory . '/en-html.html.twig'));
            $update->setDePlain($filesystem->readFile($this->plainFile($filesystem, $directory, 'de')));
            $update->setDeHtml($filesystem->readFile($directory . '/de-html.html.twig'));

            $this->updateMail($update, $connection);
        }
    }

    /**
     * One shipped type keeps its plain text under a .txt.twig name
     */
    private function plainFile(Filesystem $filesystem, string $directory, string $locale): string
    {
        $file = \sprintf('%s/%s-plain.html.twig', $directory, $locale);

        return $filesystem->exists($file) ? $file : \sprintf('%s/%s-plain.txt.twig', $directory, $locale);
    }
}
