<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1630485317UpdateContactFormMailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1630485317;
    }

    public function update(Connection $connection): void
    {
        $update = new MailUpdate(
            'contact_form',
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/contact_form/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/contact_form/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/contact_form/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/contact_form/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
