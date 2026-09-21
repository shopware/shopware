<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

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
#[Package('inventory')]
class Migration1788531858FixGaranLabelInOrderConfirmationMail extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1788531858;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-html.html.twig'),
        );

        $this->updateMail($update, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
