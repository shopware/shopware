<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Twig;

use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * This class contains the needed functions in order to do the query highlighting
 */
class DoctrineExtension extends AbstractExtension
{
    /**
     * Number of maximum characters that one single line can hold in the interface
     *
     * @var int
     */
    private $maxCharWidth = 100;

    /**
     * Define our functions
     *
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('doctrine_minify_query', [$this, 'minifyQuery'], ['deprecated' => true]),
            new TwigFilter('doctrine_pretty_query', [$this, 'formatQuery'], ['is_safe' => ['html']]),
            new TwigFilter('doctrine_replace_query_parameters', [$this, 'replaceQueryParameters']),
        ];
    }

    /**
     * Minify the query
     */
    public function minifyQuery(string $query): string
    {
        $result = '';
        $keywords = [];
        $required = 1;

        // Check if we can match the query against any of the major types
        switch (true) {
            case mb_stripos($query, 'SELECT') !== false:
                $keywords = ['SELECT', 'FROM', 'WHERE', 'HAVING', 'ORDER BY', 'LIMIT'];
                $required = 2;

                break;

            case mb_stripos($query, 'DELETE') !== false:
                $keywords = ['DELETE', 'FROM', 'WHERE', 'ORDER BY', 'LIMIT'];
                $required = 2;

                break;

            case mb_stripos($query, 'UPDATE') !== false:
                $keywords = ['UPDATE', 'SET', 'WHERE', 'ORDER BY', 'LIMIT'];
                $required = 2;

                break;

            case mb_stripos($query, 'INSERT') !== false:
                $keywords = ['INSERT', 'INTO', 'VALUE', 'VALUES'];
                $required = 2;

                break;

            // If there's no match so far just truncate it to the maximum allowed by the interface
            default:
                $result = mb_substr($query, 0, $this->maxCharWidth);
        }

        // If we had a match then we should minify it
        if ($result === '') {
            $result = $this->composeMiniQuery($query, $keywords, $required);
        }

        return $result;
    }

    /**
     * Escape parameters of a SQL query
     * DON'T USE THIS FUNCTION OUTSIDE ITS INTENDED SCOPE
     *
     * @internal
     */
    public static function escapeFunction($parameter): string
    {
        $result = $parameter;

        switch (true) {
            // Check if result is non-unicode string using PCRE_UTF8 modifier
            case \is_string($result) && !preg_match('//u', $result):
                $result = '0x' . mb_strtoupper(bin2hex($result));

                break;

            case \is_string($result):
                $result = "'" . addslashes($result) . "'";

                break;

            case \is_array($result):
                foreach ($result as &$value) {
                    $value = self::escapeFunction($value);
                }

                $result = implode(', ', $result);

                break;

            case \is_object($result):
                $result = addslashes((string) $result);

                break;

            case $result === null:
                $result = 'NULL';

                break;

            case \is_bool($result):
                $result = $result ? '1' : '0';

                break;
        }

        return (string) $result;
    }

    /**
     * Return a query with the parameters replaced
     *
     * @param array|Data $parameters
     */
    public function replaceQueryParameters(string $query, $parameters): string
    {
        $values = $parameters;

        if ($parameters instanceof Data) {
            $values = $parameters->getValue(true);
        }

        $i = 0;

        if (!\array_key_exists(0, $values) && \array_key_exists(1, $values)) {
            $i = 1;
        }

        return preg_replace_callback(
            '/\?|((?<!:):[a-z0-9_]+)/i',
            static function ($matches) use ($values, &$i) {
                $key = mb_substr($matches[0], 1);
                if (!\array_key_exists($i, $values) && (!$key || !\array_key_exists($key, $values))) {
                    return $matches[0];
                }

                $value = \array_key_exists($i, $values) ? $values[$i] : $values[$key];
                $result = self::escapeFunction($value);
                ++$i;

                return $result;
            },
            $query
        );
    }

    /**
     * Formats and/or highlights the given SQL statement.
     *
     * @param bool $highlightOnly If true the query is not formatted, just highlighted
     */
    public function formatQuery(string $sql, bool $highlightOnly = false): string
    {
        \SqlFormatter::$pre_attributes = 'class="highlight highlight-sql"';
        \SqlFormatter::$quote_attributes = 'class="string"';
        \SqlFormatter::$backtick_quote_attributes = 'class="string"';
        \SqlFormatter::$reserved_attributes = 'class="keyword"';
        \SqlFormatter::$boundary_attributes = 'class="symbol"';
        \SqlFormatter::$number_attributes = 'class="number"';
        \SqlFormatter::$word_attributes = 'class="word"';
        \SqlFormatter::$error_attributes = 'class="error"';
        \SqlFormatter::$comment_attributes = 'class="comment"';
        \SqlFormatter::$variable_attributes = 'class="variable"';

        if ($highlightOnly) {
            $html = \SqlFormatter::highlight($sql);
            $html = preg_replace('/<pre class=".*">([^"]*+)<\/pre>/Us', '\1', $html);
        } else {
            $html = \SqlFormatter::format($sql);
            $html = preg_replace('/<pre class="(.*)">([^"]*+)<\/pre>/Us', '<div class="\1"><pre>\2</pre></div>', $html);
        }

        return $html;
    }

    /**
     * Get the name of the extension
     */
    public function getName(): string
    {
        return 'doctrine_extension';
    }

    /**
     * Get the possible combinations of elements from the given array
     */
    private function getPossibleCombinations(array $elements, int $combinationsLevel): array
    {
        $baseCount = \count($elements);
        $result = [];

        if ($combinationsLevel === 1) {
            foreach ($elements as $element) {
                $result[] = [$element];
            }

            return $result;
        }

        $nextLevelElements = $this->getPossibleCombinations($elements, $combinationsLevel - 1);

        foreach ($nextLevelElements as $nextLevelElement) {
            $lastElement = $nextLevelElement[$combinationsLevel - 2];
            $found = false;

            foreach ($elements as $key => $element) {
                if ($element === $lastElement) {
                    $found = true;

                    continue;
                }

                if ($found !== true || $key >= $baseCount) {
                    continue;
                }

                $tmp = $nextLevelElement;
                $newCombination = \array_slice($tmp, 0);
                $newCombination[] = $element;
                $result[] = \array_slice($newCombination, 0);
            }
        }

        return $result;
    }

    /**
     * Shrink the values of parameters from a combination
     */
    private function shrinkParameters(array $parameters, array $combination): string
    {
        array_shift($parameters);
        $result = '';

        $maxLength = $this->maxCharWidth;
        $maxLength -= \count($parameters) * 5;
        $maxLength /= \count($parameters);

        foreach ($parameters as $key => $value) {
            $isLarger = false;

            if (mb_strlen($value) > $maxLength) {
                $value = wordwrap($value, $maxLength, "\n", true);
                $value = explode("\n", $value);
                $value = $value[0];

                $isLarger = true;
            }
            $value = self::escapeFunction($value);

            if (!is_numeric($value)) {
                $value = mb_substr($value, 1, -1);
            }

            if ($isLarger) {
                $value .= ' [...]';
            }

            $result .= ' ' . $combination[$key] . ' ' . $value;
        }

        return trim($result);
    }

    /**
     * Attempt to compose the best scenario minified query so that a user could find it without expanding it
     */
    private function composeMiniQuery(string $query, array $keywords, int $required): string
    {
        // Extract the mandatory keywords and consider the rest as optional keywords
        $mandatoryKeywords = array_splice($keywords, 0, $required);

        $combinations = [];
        $combinationsCount = \count($keywords);

        // Compute all the possible combinations of keywords to match the query for
        while ($combinationsCount > 0) {
            $combinations = array_merge($combinations, $this->getPossibleCombinations($keywords, $combinationsCount));
            --$combinationsCount;
        }

        // Try and match the best case query pattern
        foreach ($combinations as $combination) {
            $combination = array_merge($mandatoryKeywords, $combination);

            $regexp = implode('(.*) ', $combination) . ' (.*)';
            $regexp = '/^' . $regexp . '/is';

            if (preg_match($regexp, $query, $matches)) {
                return $this->shrinkParameters($matches, $combination);
            }
        }

        // Try and match the simplest query form that contains only the mandatory keywords
        $regexp = implode(' (.*)', $mandatoryKeywords) . ' (.*)';
        $regexp = '/^' . $regexp . '/is';

        if (preg_match($regexp, $query, $matches)) {
            return $this->shrinkParameters($matches, $mandatoryKeywords);
        }

        // Fallback in case we didn't managed to find any good match (can we actually have that happen?!)
        return mb_substr($query, 0, $this->maxCharWidth);
    }
}
