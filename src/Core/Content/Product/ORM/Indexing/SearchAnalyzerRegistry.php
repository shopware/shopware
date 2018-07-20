<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ORM\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;

class SearchAnalyzerRegistry
{
    /**
     * @var SearchAnalyzerInterface[]
     */
    protected $analyzers;

    public function __construct(iterable $analyzers)
    {
        $this->analyzers = $analyzers;
    }

    public function analyze(string $definition, Entity $entity, Context $context): array
    {
        $collection = [];

        foreach ($this->analyzers as $analyzer) {
            $keywords = $analyzer->analyze($definition, $entity, $context);

            foreach ($keywords as $keyword => $ranking) {
                $before = 0;

                if (array_key_exists($keyword, $collection)) {
                    $before = $collection[$keyword];
                }

                $collection[$keyword] = max($before, $ranking);
            }
        }

        return $collection;
    }
}
