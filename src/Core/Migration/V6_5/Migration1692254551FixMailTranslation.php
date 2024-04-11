<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('buyers-experience')]
class Migration1692254551FixMailTranslation extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1692254551;
    }

    public function update(Connection $connection): void
    {
        $updateAuthorizedMail = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-html.html.twig'),
        );
        $this->updateMail($updateAuthorizedMail, $connection);

        $updateChargebackMail = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-html.html.twig'),
        );
        $this->updateMail($updateChargebackMail, $connection);

        $updateUnconfirmedMail = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-html.html.twig'),
        );
        $this->updateMail($updateUnconfirmedMail, $connection);
    }
}
