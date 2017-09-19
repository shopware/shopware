<?php

namespace Shopware\SeoUrl\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SeoUrlBasicFactory extends Factory
{
    const ROOT_NAME = 'seo_url';
    const EXTENSION_NAMESPACE = 'seoUrl';

    const FIELDS = [
       'uuid' => 'uuid',
       'seo_hash' => 'seo_hash',
       'shop_uuid' => 'shop_uuid',
       'name' => 'name',
       'foreign_key' => 'foreign_key',
       'path_info' => 'path_info',
       'seo_path_info' => 'seo_path_info',
       'is_canonical' => 'is_canonical',
       'created_at' => 'created_at',
    ];

    /**
     * @var SeoUrlExtension[]
     */
    protected $extensions = [];

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(Connection $connection, ExtensionRegistryInterface $registry, ContainerInterface $container)
    {
        parent::__construct($connection, $registry);
        $this->container = $container;
    }

    public function hydrate(
        array $data,
        SeoUrlBasicStruct $seoUrl,
        QuerySelection $selection,
        TranslationContext $context
    ): SeoUrlBasicStruct {
        $seoUrl->setUuid((string) $data[$selection->getField('uuid')]);
        $seoUrl->setSeoHash((string) $data[$selection->getField('seo_hash')]);
        $seoUrl->setShopUuid((string) $data[$selection->getField('shop_uuid')]);
        $seoUrl->setName((string) $data[$selection->getField('name')]);
        $seoUrl->setForeignKey((string) $data[$selection->getField('foreign_key')]);
        $seoUrl->setPathInfo((string) $data[$selection->getField('path_info')]);
        $seoUrl->setSeoPathInfo((string) $data[$selection->getField('seo_path_info')]);
        $seoUrl->setIsCanonical((bool) $data[$selection->getField('is_canonical')]);
        $seoUrl->setCreatedAt(new \DateTime($data[$selection->getField('created_at')]));

        $routerContext = $this->container->get('router')->getContext();
        $url = implode('/', array_filter([$routerContext->getBaseUrl(), $seoUrl->getSeoPathInfo()]));
        $url = sprintf('%s://%s/%s', $routerContext->getScheme(), $routerContext->getHost(), $url);
        $seoUrl->setUrl($url);

        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($seoUrl, $data, $selection, $context);
        }

        return $seoUrl;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'seo_url_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.seo_url_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

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
}
