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

        $acceptedUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_ACCEPTED);
        $acceptedUpdate->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.accepted/en-plain.html.twig'));
        $acceptedUpdate->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.accepted/en-html.html.twig'));
        $acceptedUpdate->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.accepted/de-plain.html.twig'));
        $acceptedUpdate->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.accepted/de-html.html.twig'));
        $this->updateMail($acceptedUpdate, $connection);

        $declinedUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_DECLINED);
        $declinedUpdate->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.declined/en-plain.html.twig'));
        $declinedUpdate->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.declined/en-html.html.twig'));
        $declinedUpdate->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.declined/de-plain.html.twig'));
        $declinedUpdate->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.group.registration.declined/de-html.html.twig'));
        $this->updateMail($declinedUpdate, $connection);
    }
}
