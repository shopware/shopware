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
 * Adds the producer's guarantee terms (PDF and URL) to products and links them in unedited order confirmation mails.
 *
 * @internal
 */
#[Package('inventory')]
class Migration1790906511AddGaranGuaranteeTerms extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1790906511;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product', 'guarantee_terms_media_id', 'BINARY(16)');
        $this->addColumn($connection, 'product', 'guarantee_terms_url', 'VARCHAR(2048)');

        // inheritance column of the `guaranteeTermsMedia` association, no indexing needed as no product has terms yet
        $this->addColumn($connection, 'product', 'guaranteeTermsMedia', 'BINARY(16)');

        if (!$this->indexExists($connection, 'product', 'fk.product.guarantee_terms_media_id')) {
            $this->executeDdlStatement(
                $connection,
                'ALTER TABLE `product`
                ADD CONSTRAINT `fk.product.guarantee_terms_media_id`
                    FOREIGN KEY (`guarantee_terms_media_id`)
                    REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }

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
}
