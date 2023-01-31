<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Twig;

use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('core
This class contains the needed functions in order to do the query highlighting')]
class DoctrineExtension extends AbstractExtension
{
    private SqlFormatter $sqlFormatter;

    /**
     * Define our functions
     *
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('doctrine_prettify_sql', $this->prettifySql(...), ['is_safe' => ['html']]),
            new TwigFilter('doctrine_format_sql', $this->formatSql(...), ['is_safe' => ['html']]),
            new TwigFilter('doctrine_replace_query_parameters', $this->replaceQueryParameters(...)),
        ];
    }

    /**
     * Escape parameters of a SQL query
     * DON'T USE THIS FUNCTION OUTSIDE ITS INTENDED SCOPE
     *
     * @internal
     */
    public static function escapeFunction(mixed $parameter): string
    {
        $result = $parameter;

        switch (true) {
            // Check if result is non-unicode string using PCRE_UTF8 modifier
            case \is_string($result) && !preg_match('//u', $result):
                $result = '0x' . strtoupper(bin2hex($result));

                break;

            case \is_string($result):
                $result = '\'' . addslashes($result) . '\'';

                break;

            case \is_array($result):
                foreach ($result as &$value) {
                    $value = static::escapeFunction($value);
                }

                $result = implode(', ', $result) ?: 'NULL';

                break;

            case \is_object($result) && method_exists($result, '__toString'):
                $result = addslashes((string) $result->__toString());

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
     * @param array<mixed>|Data $parameters
     */
    public function replaceQueryParameters(string $query, array|Data $parameters = []): string
    {
        if ($parameters instanceof Data) {
            $parameters = $parameters->getValue(true);
            /** @var array<mixed> $parameters */
        }

        $i = 0;

        if (!\array_key_exists(0, $parameters) && \array_key_exists(1, $parameters)) {
            $i = 1;
        }

        return (string) preg_replace_callback(
            '/\?|((?<!:):[a-z0-9_]+)/i',
            static function ($matches) use ($parameters, &$i) {
                $key = substr($matches[0], 1);

                if (!\array_key_exists($i, $parameters) && !\array_key_exists($key, $parameters)) {
                    return $matches[0];
                }

                $value = \array_key_exists($i, $parameters) ? $parameters[$i] : $parameters[$key];
                $result = DoctrineExtension::escapeFunction($value);
                ++$i;

                return $result;
            },
            $query
        );
    }

    public function prettifySql(string $sql): string
    {
        $this->setUpSqlFormatter();

        return $this->sqlFormatter->highlight($sql);
    }

    public function formatSql(string $sql, bool $highlight): string
    {
        $this->setUpSqlFormatter($highlight);

        return $this->sqlFormatter->format($sql);
    }

    /**
     * Get the name of the extension
     */
    public function getName(): string
    {
        return 'doctrine_extension';
    }

    private function setUpSqlFormatter(bool $highlight = true, bool $legacy = false): void
    {
        $this->sqlFormatter = new SqlFormatter($highlight ? new HtmlHighlighter([
            HtmlHighlighter::HIGHLIGHT_PRE => 'class="highlight highlight-sql"',
            HtmlHighlighter::HIGHLIGHT_QUOTE => 'class="string"',
            HtmlHighlighter::HIGHLIGHT_BACKTICK_QUOTE => 'class="string"',
            HtmlHighlighter::HIGHLIGHT_RESERVED => 'class="keyword"',
            HtmlHighlighter::HIGHLIGHT_BOUNDARY => 'class="symbol"',
            HtmlHighlighter::HIGHLIGHT_NUMBER => 'class="number"',
            HtmlHighlighter::HIGHLIGHT_WORD => 'class="word"',
            HtmlHighlighter::HIGHLIGHT_ERROR => 'class="error"',
            HtmlHighlighter::HIGHLIGHT_COMMENT => 'class="comment"',
            HtmlHighlighter::HIGHLIGHT_VARIABLE => 'class="variable"',
        ], !$legacy) : new NullHighlighter());
    }
}
