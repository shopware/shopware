<?php declare(strict_types=1);

namespace Shopware\ProductVote\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\ProductVote\Extension\ProductVoteExtension;
use Shopware\ProductVote\Struct\ProductVoteBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ProductVoteBasicFactory extends Factory
{
    const ROOT_NAME = 'product_vote';
    const EXTENSION_NAMESPACE = 'productVote';

    const FIELDS = [
       'uuid' => 'uuid',
       'name' => 'name',
       'productUuid' => 'product_uuid',
       'headline' => 'headline',
       'comment' => 'comment',
       'points' => 'points',
       'active' => 'active',
       'email' => 'email',
       'answer' => 'answer',
       'answeredAt' => 'answered_at',
       'shopUuid' => 'shop_uuid',
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
        ProductVoteBasicStruct $productVote,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductVoteBasicStruct {
        $productVote->setUuid((string) $data[$selection->getField('uuid')]);
        $productVote->setName((string) $data[$selection->getField('name')]);
        $productVote->setProductUuid((string) $data[$selection->getField('productUuid')]);
        $productVote->setHeadline((string) $data[$selection->getField('headline')]);
        $productVote->setComment((string) $data[$selection->getField('comment')]);
        $productVote->setPoints((float) $data[$selection->getField('points')]);
        $productVote->setActive((int) $data[$selection->getField('active')]);
        $productVote->setEmail((string) $data[$selection->getField('email')]);
        $productVote->setAnswer(isset($data[$selection->getField('answer')]) ? (string) $data[$selection->getField('answer')] : null);
        $productVote->setAnsweredAt(isset($data[$selection->getField('answeredAt')]) ? new \DateTime($data[$selection->getField('answeredAt')]) : null);
        $productVote->setShopUuid(isset($data[$selection->getField('shopUuid')]) ? (string) $data[$selection->getField('shopUuid')] : null);
        $productVote->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $productVote->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

        /** @var $extension ProductVoteExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($productVote, $data, $selection, $context);
        }

        return $productVote;
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
            'product_vote_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.product_vote_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
