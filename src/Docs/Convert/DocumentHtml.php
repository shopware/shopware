<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class DocumentHtml
{
    private const METATAG_REGEX = '/^\[(.*?)\]:\s*<>\((.*?)\)\s*?$/m';

    private const RAW_TRIGGER = '[__RAW__]: <>(__RAW__)';

    private Document $document;

    private array $media = [];

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function render(DocumentTree $documentTree): RenderedDocument
    {
        $contents = $this->document
            ->getFile()
            ->getContents();

        return $this->convertMarkdownToHtml($contents, $documentTree);
    }

    private function stripMetatags(string $fileContents): string
    {
        return preg_replace(self::METATAG_REGEX, '', $fileContents);
    }

    private function convertMarkdownToHtml(string $contents, DocumentTree $documentTree): RenderedDocument
    {
        $this->media = [];

        if (mb_strpos($contents, self::RAW_TRIGGER) !== false) {
            $parts = explode(self::RAW_TRIGGER, $contents);
            $html = end($parts);
        } else {
            $contents = $this->stripMetatags($contents);
            $html = (new DocsParsedownExtra($this->document->getFile()))->parse($contents);
        }

        $relativeLinkReplacementRegex = '/(?:href|src)=\"(.*?)\"/m';
        $out = preg_replace_callback(
            $relativeLinkReplacementRegex,
            function ($match) use ($documentTree) {
                return $this->replaceRelativeLinks($match, $documentTree);
            },
            $html
        );

        return new RenderedDocument($out, $this->media);
    }

    private function replaceRelativeLinks(array $matches, DocumentTree $tree): string
    {
        $link = $matches[1];
        $linkParts = explode('#', $link);
        $linkHref = $linkParts[0];
        $linkAnchor = \count($linkParts) > 1 ? $linkParts[1] : '';

        if (mb_strpos($link, 'http://') === 0) {
            return $matches[0];
        }

        if (mb_strpos($link, 'https://') === 0) {
            return $matches[0];
        }

//        if(strpos($linkHref, './') !== 0) {
//            throw new \RuntimeException(sprintf('Unknown link format %s in %s', $linkHref, $this->document->getFile()->getRealPath()));
//        }

        if (pathinfo($linkHref, \PATHINFO_EXTENSION) === 'md') {
            return $this->resolveLinkUrl($matches, $tree, $linkHref, $linkAnchor);
        }

        if (\in_array(pathinfo($linkHref, \PATHINFO_EXTENSION), ['svg', 'png', 'jpg', 'jpeg'], true)) {
            return $this->resolveMedia($matches, $linkHref);
        }

        return $matches[0];
    }

    private function toAbsolutePath(string $link): string
    {
        return \dirname($this->document->getFile()->getRealPath()) . mb_substr($link, 1);
    }

    private function resolveMedia(array $matches, string $link): string
    {
        $key = 'MEDIAITEM' . \count($this->media);

        $image = \dirname($this->document->getFile()->getRealPath()) . mb_substr($link, 1);

        if (!file_exists($image)) {
            throw new \RuntimeException(sprintf('Unable to find and therefore link %s on %s', $image, $this->document->getFile()->getRelativePathname()));
        }

        $this->media[$key] = $image;

        return str_replace($matches[1], $key, $matches[0]);
    }

    private function fixLinkAnchors(string $anchor): string
    {
        return '#' . mb_strtolower(str_replace('.', '-', $anchor));
    }

    private function resolveLinkUrl(array $matches, DocumentTree $tree, string $linkHref, string $linkAnchor): string
    {
        $absolutePath = $this->toAbsolutePath($linkHref);

        try {
            $relatedDocument = $tree->findByAbsolutePath($absolutePath);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(
                sprintf(
                    'No file found named %s referenced from %s',
                    $absolutePath,
                    $this->document->getFile()->getRealPath()
                ),
                0,
                $e
            );
        }

        $url = $relatedDocument->getMetadata()->getPrefixedUrlEn();

        if ($linkAnchor) {
            return str_replace($matches[1], $url . $this->fixLinkAnchors($linkAnchor), $matches[0]);
        }

        return str_replace($matches[1], $url, $matches[0]);
    }
}
