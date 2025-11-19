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
class Migration1763544592UpdateGroupRegistrationMailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1763544592;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.accepted/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.accepted/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.accepted/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.accepted/de-html.html.twig')
        );
        $this->updateMail($update, $connection);

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.declined/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.declined/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.declined/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.declined/de-html.html.twig')
        );
        $this->updateMail($update, $connection);
    }
}
