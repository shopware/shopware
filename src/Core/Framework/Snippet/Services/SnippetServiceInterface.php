<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Services;

use Shopware\Core\Framework\Context;
use Symfony\Component\Translation\MessageCatalogueInterface;

interface SnippetServiceInterface
{
    /**
     * @param int     $page
     * @param int     $limit
     * @param Context $context
     * @param array   $filters
     *
     * filters: [
     *      'isCustom' => bool,
     *      'isEmpty' => bool,
     *      'term' => string,
     *      'namespaces' => array,
     *      'authors' => array,
     *      'translationKeys' => array,
     * ]
     *
     * @return array
     */
    public function getList(
        int $page,
        int $limit,
        Context $context,
        array $filters
    ): array;

    /**
     * @param MessageCatalogueInterface $catalog
     * @param string                    $snippetSetId
     *
     * @return array
     */
    public function getStorefrontSnippets(MessageCatalogueInterface $catalog, string $snippetSetId): array;

    /**
     * @param Context $context
     *
     * @return array
     */
    public function getRegionFilterItems(Context $context): array;
}
