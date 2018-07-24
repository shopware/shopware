<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search\Util;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Search\Term\SearchFilterInterface;
use Shopware\Core\Framework\ORM\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;

class LanguageAnalyzer implements SearchAnalyzerInterface
{
    /**
     * @var TokenizerInterface
     */
    private $tokenizer;

    /**
     * @var SearchFilterInterface
     */
    private $filter;

    public function __construct(TokenizerInterface $tokenizer, SearchFilterInterface $filter)
    {
        $this->tokenizer = $tokenizer;
        $this->filter = $filter;
    }

    public function analyze(string $definition, Entity $entity, Context $context): array
    {
        return $this->analyzeEntity($definition, $entity, $context);
    }

    private function analyzeEntity(string $definition, Entity $entity, Context $context, ?float $multiplier = null): array
    {
        $allowsRecursive = false;
        if ($multiplier === null) {
            $allowsRecursive = true;
        }

        $multiplier = $multiplier ?? 1.0;

        $tokens = [];

        /** @var string|EntityDefinition $definition */
        $fields = $definition::getSearchFields();

        foreach ($fields as $field) {
            $value = $entity->get($field->getPropertyName());

            if (!$value) {
                continue;
            }
            /** @var SearchRanking $flag */
            $flag = $field->getFlag(SearchRanking::class);

            if (!$field instanceof AssociationInterface) {
                $fieldTokens = $this->tokenizer->tokenize((string) $value);

                $score = $flag ? $flag->getRanking() : 100;
                $score *= $multiplier;

                $tokens = $this->mergeTokens($tokens, $fieldTokens, $score);

                continue;
            }

            if (!$allowsRecursive) {
                continue;
            }

            $nested = $this->analyzeEntity(
                $field->getReferenceClass(),
                $value,
                $context,
                $flag->getRanking()
            );

            foreach ($nested as $token => $ranking) {
                if (isset($tokens[$token])) {
                    $tokens[$token] = $tokens[$token] > $ranking ? $tokens[$token] : $ranking;
                } else {
                    $tokens[$token] = $ranking;
                }
            }
        }

        return $this->filter->filter($tokens, $context);
    }

    private function mergeTokens(array $existing, array $new, float $ranking): array
    {
        foreach ($new as $keyword) {
            if (isset($existing[$keyword])) {
                $existing[$keyword] = $existing[$keyword] > $ranking ? $existing[$keyword] : $ranking;
            } else {
                $existing[$keyword] = $ranking;
            }
        }

        return $existing;
    }
}
