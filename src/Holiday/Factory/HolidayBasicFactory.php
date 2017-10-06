<?php declare(strict_types=1);

namespace Shopware\Holiday\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Holiday\Extension\HolidayExtension;
use Shopware\Holiday\Struct\HolidayBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class HolidayBasicFactory extends Factory
{
    const ROOT_NAME = 'holiday';
    const EXTENSION_NAMESPACE = 'holiday';

    const FIELDS = [
       'uuid' => 'uuid',
       'calculation' => 'calculation',
       'eventDate' => 'event_date',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'name' => 'translation.name',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        HolidayBasicStruct $holiday,
        QuerySelection $selection,
        TranslationContext $context
    ): HolidayBasicStruct {
        $holiday->setUuid((string) $data[$selection->getField('uuid')]);
        $holiday->setCalculation((string) $data[$selection->getField('calculation')]);
        $holiday->setEventDate(new \DateTime($data[$selection->getField('eventDate')]));
        $holiday->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $holiday->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $holiday->setName((string) $data[$selection->getField('name')]);

        /** @var $extension HolidayExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($holiday, $data, $selection, $context);
        }

        return $holiday;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'holiday_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.holiday_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
