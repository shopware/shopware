<?php declare(strict_types=1);

namespace Shopware\Locale\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Locale\Extension\LocaleExtension;
use Shopware\Locale\Struct\LocaleBasicStruct;

class LocaleBasicFactory extends Factory
{
    const ROOT_NAME = 'locale';
    const EXTENSION_NAMESPACE = 'locale';

    const FIELDS = [
       'uuid' => 'uuid',
       'code' => 'code',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'language' => 'translation.language',
       'territory' => 'translation.territory',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        LocaleBasicStruct $locale,
        QuerySelection $selection,
        TranslationContext $context
    ): LocaleBasicStruct {
        $locale->setUuid((string) $data[$selection->getField('uuid')]);
        $locale->setCode((string) $data[$selection->getField('code')]);
        $locale->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $locale->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $locale->setLanguage((string) $data[$selection->getField('language')]);
        $locale->setTerritory((string) $data[$selection->getField('territory')]);

        /** @var $extension LocaleExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($locale, $data, $selection, $context);
        }

        return $locale;
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
            'locale_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.locale_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
