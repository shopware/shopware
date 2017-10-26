<?php declare(strict_types=1);

namespace Shopware\Category\Extension;

use Doctrine\DBAL\Connection;
use Shopware\Category\Event\CategoryWrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategoryPathExtension implements EventSubscriberInterface
{
    /**
     * @var CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        CategoryPathBuilder $pathBuilder,
        Connection $connection
    ) {
        $this->pathBuilder = $pathBuilder;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            CategoryWrittenEvent::NAME => 'categoryWritten',
        ];
    }

    public function categoryWritten(CategoryWrittenEvent $event): void
    {
        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);

        $parentUuids = $this->fetchParents($event->getUuids());

        foreach ($parentUuids as $uuid) {
            $this->pathBuilder->update($uuid, $context);
        }
    }

    private function fetchParents(array $uuids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['parent_uuid']);
        $query->from('category');
        $query->where('category.uuid IN (:uuids)');
        $query->setParameter('uuids', $uuids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }
}
