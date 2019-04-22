<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Filter;

use Shopware\Core\Framework\Snippet\Exception\FilterNotFoundException;

class SnippetFilterFactory
{
    /**
     * @var array
     */
    private $filters = [];

    public function __construct(iterable $filters)
    {
        $this->filters = $filters;
    }

    /**
     * @throws \Exception
     */
    public function getFilter(string $name): SnippetFilterInterface
    {
        /** @var SnippetFilterInterface $filter */
        foreach ($this->filters as $filter) {
            if ($filter->supports($name)) {
                return $filter;
            }
        }

        throw new FilterNotFoundException($name, __CLASS__);
    }
}
