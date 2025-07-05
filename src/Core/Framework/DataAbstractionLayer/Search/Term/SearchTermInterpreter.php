<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Product\SearchConfigLoader;

#[Package('framework')]
class SearchTermInterpreter
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TokenizerInterface $tokenizer,
        private readonly SearchConfigLoader $configLoader
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $context will be required
     */
    public function interpret(string $term/* , Context $context */): SearchPattern
    {
        $config = null;
        if (\func_num_args() === 2) {
            $context = func_get_arg(1);
            $config = $this->configLoader->loadFilterConfig($context->getLanguageId());
        }

        $terms = $this->tokenizer->tokenize($term, $config['minSearchLength'] ?? null);

        $pattern = new SearchPattern(new SearchTerm($term));

        if (\count($terms) === 1) {
            return $pattern;
        }

        foreach ($terms as $part) {
            $percent = mb_strlen($part) / mb_strlen($term);
            $pattern->addTerm(new SearchTerm($part, $percent));
        }

        return $pattern;
    }
}
