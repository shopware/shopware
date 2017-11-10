<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ShopTemplate\Extension\ShopTemplateExtension;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicStruct;

class ShopTemplateBasicFactory extends Factory
{
    const ROOT_NAME = 'shop_template';
    const EXTENSION_NAMESPACE = 'shopTemplate';

    const FIELDS = [
       'uuid' => 'uuid',
       'template' => 'template',
       'name' => 'name',
       'description' => 'description',
       'author' => 'author',
       'license' => 'license',
       'esi' => 'esi',
       'styleSupport' => 'style_support',
       'version' => 'version',
       'emotion' => 'emotion',
       'pluginUuid' => 'plugin_uuid',
       'parentUuid' => 'parent_uuid',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        ShopTemplateBasicStruct $shopTemplate,
        QuerySelection $selection,
        TranslationContext $context
    ): ShopTemplateBasicStruct {
        $shopTemplate->setUuid((string) $data[$selection->getField('uuid')]);
        $shopTemplate->setTemplate((string) $data[$selection->getField('template')]);
        $shopTemplate->setName((string) $data[$selection->getField('name')]);
        $shopTemplate->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $shopTemplate->setAuthor(isset($data[$selection->getField('author')]) ? (string) $data[$selection->getField('author')] : null);
        $shopTemplate->setLicense(isset($data[$selection->getField('license')]) ? (string) $data[$selection->getField('license')] : null);
        $shopTemplate->setEsi((bool) $data[$selection->getField('esi')]);
        $shopTemplate->setStyleSupport((bool) $data[$selection->getField('styleSupport')]);
        $shopTemplate->setVersion((int) $data[$selection->getField('version')]);
        $shopTemplate->setEmotion((bool) $data[$selection->getField('emotion')]);
        $shopTemplate->setPluginUuid(isset($data[$selection->getField('pluginUuid')]) ? (string) $data[$selection->getField('pluginUuid')] : null);
        $shopTemplate->setParentUuid(isset($data[$selection->getField('parentUuid')]) ? (string) $data[$selection->getField('parentUuid')] : null);
        $shopTemplate->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $shopTemplate->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

        /** @var $extension ShopTemplateExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($shopTemplate, $data, $selection, $context);
        }

        return $shopTemplate;
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
            'shop_template_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.shop_template_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
