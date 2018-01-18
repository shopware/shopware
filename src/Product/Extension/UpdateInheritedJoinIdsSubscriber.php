<?php

namespace Shopware\Product\Extension;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Product\Event\Product\ProductWrittenEvent;
use Shopware\Api\Product\Event\ProductMedia\ProductMediaWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UpdateInheritedJoinIdsSubscriber implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductWrittenEvent::NAME => 'updateProduct',
            ProductMediaWrittenEvent::NAME => 'updateMedia'
        ];
    }

    public function updateMedia(ProductMediaWrittenEvent $event)
    {
        $ids = $event->getIds();
        $bytes = array_map(function($id) {
            return Uuid::fromString($id)->getBytes();
        }, $ids);

        $this->connection->executeUpdate('
            UPDATE product, product_media
            SET product.media_join_id = product_media.product_id
            WHERE product_media.id IN (:ids)
            AND product_media.product_id = product.id
        ',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    public function updateProduct(ProductWrittenEvent $event)
    {
        $ids = $event->getIds();
        $bytes = array_map(function($id) {
            return Uuid::fromString($id)->getBytes();
        }, $ids);

        $this->connection->executeUpdate('
            UPDATE product SET product.media_join_id = IFNULL(
                (SELECT product_media.product_id FROM product_media WHERE product_media.product_id = product.id LIMIT 1),
                product.parent_id
            )
            WHERE product.id IN (:ids)
        ',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

}