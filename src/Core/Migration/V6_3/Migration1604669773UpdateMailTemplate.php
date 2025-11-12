<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class Migration1604669773UpdateMailTemplate extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1604669773;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            'contact_form',
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/contact_form/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/contact_form/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/contact_form/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/contact_form/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
