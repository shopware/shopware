<?php declare(strict_types=1);

namespace Shopware\Locale\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Locale\Factory\LocaleBasicFactory;
use Shopware\Locale\Struct\LocaleBasicCollection;
use Shopware\Locale\Struct\LocaleBasicStruct;

class LocaleBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var LocaleBasicFactory
     */
    private $factory;

    public function __construct(
        LocaleBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): LocaleBasicCollection
    {
        if (empty($uuids)) {
            return new LocaleBasicCollection();
        }

        $localesCollection = $this->read($uuids, $context);

        return $localesCollection;
    }

    private function read(array $uuids, TranslationContext $context): LocaleBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('locale.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new LocaleBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new LocaleBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
