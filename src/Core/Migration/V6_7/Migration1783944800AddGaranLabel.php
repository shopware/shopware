<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1783944800AddGaranLabel extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1783944800;
    }

    public function update(Connection $connection): void
    {
        $this->updateProductWithHarmonisedLabelFields($connection);
        $this->updateConfirmationMailsWithHarmonisedLabels($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function updateProductWithHarmonisedLabelFields(Connection $connection): void
    {
        if ($this->columnExists($connection, 'product', 'guarantee_confirmed')) {
            return;
        }

        $this->addColumn($connection, 'product', 'guarantee_confirmed', 'TINYINT(1)', false, '0');

        if ($this->columnExists($connection, 'product', 'guarantee_months')) {
            return;
        }

        $this->addColumn($connection, 'product', 'guarantee_months', 'INT');
    }

    private function updateConfirmationMailsWithHarmonisedLabels(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            'order_confirmation_mail',
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-html.html.twig'),
        );

        $this->updateMail($update, $connection);
    }
}
