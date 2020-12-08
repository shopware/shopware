<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

class ChangelogParser
{
    public function parse(string $content): ChangelogDefinition
    {
        $content = trim($content);

        return (new ChangelogDefinition())
            ->setTitle($this->parseMetadata($content, 'title') ?: '')
            ->setIssue($this->parseMetadata($content, 'issue') ?: '')
            ->setFlag($this->parseMetadata($content, 'flag'))
            ->setAuthor($this->parseMetadata($content, 'author'))
            ->setAuthorEmail($this->parseMetadata($content, 'author_email'))
            ->setAuthorGitHub($this->parseMetadata($content, 'author_github'))
            ->setCore($this->parseSection($content, 'Core'))
            ->setAdministration($this->parseSection($content, 'Administration'))
            ->setStorefront($this->parseSection($content, 'Storefront'))
            ->setApi($this->parseSection($content, 'API'))
            ->setUpgradeInformation($this->parseSection($content, 'Upgrade Information'));
    }

    /**
     * Retrieve the metadata of changelog
     */
    private function parseMetadata(string $content, string $meta): ?string
    {
        preg_match('/^' . $meta . '\s*:([^\n]+)$/im', $content, $matches);

        return isset($matches[1]) ? trim($matches[1]) : null;
    }

    /**
     * Retrieve the content of a given section
     * !!!NOTE: Due to PCRE limit, we CANNOT use Regular Expression here for a long content
     *     preg_match('/#\s' . $section . '\s*\n((\n|.)+)((___)|$)/iU', $content, $matches);
     */
    private function parseSection(string $content, string $section): ?string
    {
        $startPos = strpos($content, '# ' . $section);
        if ($startPos === false) {
            return null;
        }
        $endPos = strpos($content, '___', $startPos + \strlen('# ' . $section)) ?: 0;
        if ($endPos) {
            $length = $endPos - $startPos - \strlen('# ' . $section);
            $matches = substr($content, $startPos + \strlen('# ' . $section), $length);
        } else {
            $matches = substr($content, $startPos + \strlen('# ' . $section));
        }

        return trim($matches);
    }
}
