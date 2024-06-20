<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1642517958AddCascadeDeleteToTagRelations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1642517958;
    }

    public function update(Connection $connection): void
    {
        $this->dropForeignKeyIfExists($connection, 'product_tag', 'fk.product_tag.tag_id');
        $connection->executeStatement('ALTER TABLE `product_tag` ADD CONSTRAINT `fk.product_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');

        $this->dropForeignKeyIfExists($connection, 'order_tag', 'fk.order_tag.tag_id');
        $connection->executeStatement('ALTER TABLE `order_tag` ADD CONSTRAINT `fk.order_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');

        $this->dropForeignKeyIfExists($connection, 'category_tag', 'fk.category_tag.tag_id');
        $connection->executeStatement('ALTER TABLE `category_tag` ADD CONSTRAINT `fk.category_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');

        $this->dropForeignKeyIfExists($connection, 'customer_tag', 'fk.customer_tag.tag_id');
        $connection->executeStatement('ALTER TABLE `customer_tag` ADD CONSTRAINT `fk.customer_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');

        $this->dropForeignKeyIfExists($connection, 'landing_page_tag', 'fk.landing_page_tag.tag_id');
        $connection->executeStatement('ALTER TABLE `landing_page_tag` ADD CONSTRAINT `fk.landing_page_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');

        $this->dropForeignKeyIfExists($connection, 'media_tag', 'fk.media_tag.tag_id');
        $connection->executeStatement('ALTER TABLE `media_tag` ADD CONSTRAINT `fk.media_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');

        $this->dropForeignKeyIfExists($connection, 'newsletter_recipient_tag', 'fk.newsletter_recipient_tag.tag_id');
        $connection->executeStatement('ALTER TABLE `newsletter_recipient_tag` ADD CONSTRAINT `fk.newsletter_recipient_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');

        $this->dropForeignKeyIfExists($connection, 'shipping_method_tag', 'fk.shipping_method_tag.tag_id');
        $connection->executeStatement('ALTER TABLE `shipping_method_tag` ADD CONSTRAINT `fk.shipping_method_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');
    }
}
