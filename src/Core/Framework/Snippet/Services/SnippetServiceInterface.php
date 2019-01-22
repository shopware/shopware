<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Translation\MessageCatalogueInterface;

interface SnippetServiceInterface
{
    /**
     * @param Criteria $criteria
     *
     * @return array
     */
    public function getList(Criteria $criteria, Context $context): array;

    /**
     * @param Criteria $criteria
     * @param string   $author
     *
     * @return array
     */
    public function getCustomList(Criteria $criteria, string $author): array;

    /**
     * @param string $translationKey
     * @param string $author
     *
     * @return array
     */
    public function getDbSnippetByKey(string $translationKey, string $author): array;

    /**
     * @param MessageCatalogueInterface $catalog
     * @param string                    $snippetSetId
     *
     * @return array
     */
    public function getStorefrontSnippets(MessageCatalogueInterface $catalog, string $snippetSetId): array;
}
