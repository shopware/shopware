<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 */
#[Package('discovery')]
final class SnippetSetResultList extends Struct
{
    /**
     * @var list<SnippetSetResult>
     */
    public array $sets;

    public function __construct(SnippetSetResult ...$sets)
    {
        $this->sets = array_values($sets);
    }

    public function getApiAlias(): string
    {
        return 'snippet_set_result_list';
    }
}
