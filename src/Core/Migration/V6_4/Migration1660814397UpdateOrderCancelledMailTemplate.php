<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
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
class Migration1660814397UpdateOrderCancelledMailTemplate extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1660814397;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
