<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class SnippetSetResultList extends Struct
{
    /**
     * @param list<SnippetSetResult> $sets
     */
    public function __construct(public array $sets)
    {
    }

    public function getApiAlias(): string
    {
        return 'snippet_set_result_list';
    }
}
