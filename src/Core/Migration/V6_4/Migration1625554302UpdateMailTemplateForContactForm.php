<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1625554302UpdateMailTemplateForContactForm extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1625554302;
    }

    public function update(Connection $connection): void
    {
        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_CONTACT_FORM,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/contact_form/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/contact_form/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/contact_form/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/contact_form/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
