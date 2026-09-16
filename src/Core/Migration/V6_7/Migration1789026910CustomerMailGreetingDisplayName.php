<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
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
class Migration1789026910CustomerMailGreetingDisplayName extends MigrationStep
{
    use UpdateMailTrait;

    /**
     * The fixtures of customer.recovery.request and customer_register.double_opt_in are new: their shipped text
     * lived only inline in Migration1570622696CustomerPasswordRecovery and
     * Migration1572425108AddDoubleOptInRegistrationMailTemplate, and MailUpdate needs the whole body per language.
     */
    private const MAIL_TYPES = [
        MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_ACCEPTED,
        MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_DECLINED,
        MailTemplateTypes::MAILTYPE_CUSTOMER_RECOVERY_REQUEST,
        MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER_DOUBLE_OPT_IN,
        MailTemplateTypes::MAILTYPE_GUEST_ORDER_DOUBLE_OPT_IN,
        MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE,
        CustomerPasswordChangedEvent::EVENT_NAME,
    ];

    public function getCreationTimestamp(): int
    {
        return 1789026910;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        foreach (self::MAIL_TYPES as $type) {
            $update = new MailUpdate($type);
            $update->setEnPlain($filesystem->readFile(\sprintf('%s/../Fixtures/mails/%s/en-plain.html.twig', __DIR__, $type)));
            $update->setEnHtml($filesystem->readFile(\sprintf('%s/../Fixtures/mails/%s/en-html.html.twig', __DIR__, $type)));
            $update->setDePlain($filesystem->readFile(\sprintf('%s/../Fixtures/mails/%s/de-plain.html.twig', __DIR__, $type)));
            $update->setDeHtml($filesystem->readFile(\sprintf('%s/../Fixtures/mails/%s/de-html.html.twig', __DIR__, $type)));

            $this->updateMail($update, $connection);
        }
    }
}
