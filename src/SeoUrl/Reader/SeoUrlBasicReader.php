<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\SeoUrl\Factory\SeoUrlBasicFactory;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;

class SeoUrlBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var SeoUrlBasicFactory
     */
    private $factory;

    public function __construct(
        SeoUrlBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): SeoUrlBasicCollection
    {
        if (empty($uuids)) {
            return new SeoUrlBasicCollection();
        }

        $seoUrlsCollection = $this->read($uuids, $context);

        return $seoUrlsCollection;
    }

    private function read(array $uuids, TranslationContext $context): SeoUrlBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('seo_url.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new SeoUrlBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new SeoUrlBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
