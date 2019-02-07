<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet;

use Shopware\Core\Framework\Context;
use Symfony\Component\Translation\MessageCatalogueInterface;

interface SnippetServiceInterface
{
    /**
     * filters: [
     *      'isCustom' => bool,
     *      'isEmpty' => bool,
     *      'term' => string,
     *      'namespaces' => array,
     *      'authors' => array,
     *      'translationKeys' => array,
     * ]
     */
    public function getList(int $page, int $limit, Context $context, array $filters): array;

    public function getStorefrontSnippets(MessageCatalogueInterface $catalog, string $snippetSetId): array;

    public function getRegionFilterItems(Context $context): array;

    public function getAuthors(Context $context): array;
}
