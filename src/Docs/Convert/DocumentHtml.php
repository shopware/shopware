<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class DocumentHtml
{
    private const METATAG_REGEX = '/^\[(.*?)\]:\s*<>\((.*?)\)\s*?$/m';

    /**
     * @var Document
     */
    private $document;

    private $media = [];

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function render(DocumentTree $documentTree): RenderedDocument
    {
        $contents = $this->document->getFile()->getContents();

        $contents = $this->stripMetatags($contents);

        return $this->convertMarkdownToHtml($contents, $documentTree);
    }

    private function stripMetatags(string $fileContents): string
    {
        return preg_replace(self::METATAG_REGEX, '', $fileContents);
    }

    private function convertMarkdownToHtml(string $contents, DocumentTree $documentTree): RenderedDocument
    {
        $this->media = [];
        $parsedown = new \ParsedownExtra();

        $html = $parsedown->parse($contents);

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
        $linkAnchor = count($linkParts) > 1 ? $linkParts[1] : '';

        if (strpos($link, 'http://') === 0) {
            return $matches[0];
        }

        if (strpos($link, 'https://') === 0) {
            return $matches[0];
        }

//        if(strpos($linkHref, './') !== 0) {
//            throw new \RuntimeException(sprintf('Unknown link format %s in %s', $linkHref, $this->document->getFile()->getRealPath()));
//        }

        if (pathinfo($linkHref, PATHINFO_EXTENSION) === 'md') {
            return $this->resolveLinkUrl($matches, $tree, $linkHref, $linkAnchor);
        }

        if (in_array(pathinfo($linkHref, PATHINFO_EXTENSION), ['svg', 'png', 'jpg', 'jpeg'], true)) {
            return $this->resolveMedia($matches, $linkHref);
        }

        return $matches[0];
//        die;
//        $related = $this->toAbsolutePath($linkHref);
//
//        var_dump($linkAnchor);
//        return '';
//
//        $referencedFile = realpath(dirname($file) . '/' . $linkHref);
//
//        if (preg_match('/(?:\.\/.*)\.md(?:#.*)?$/m', $linkHref) === 1) {
//            if ($referencedFile !== false || strlen($linkAnchor) !== 0) {
//                return $this->replaceLinkToMarkdown($matches, $file, $metadata, $linkAnchor, $referencedFile);
//            }
//
//            $this->warningStack[] = vsprintf('The markdownfile "%s" referenced in "%s" does not exist !', [$linkHref, $file]);
//
//            return $matches[0];
//        }
//
//        if ($linkAnchor !== '') {
//            return str_replace($matches[1], $this->fixLinkAnchors($linkAnchor), $matches[0]);
//        }
//
//        if ($referencedFile !== false) {
//            return $this->replaceLinkToMedia($metadata, $matches, $file, $referencedFile);
//        }
//
//        return $matches[0];
    }

    private function toAbsolutePath(string $link): string
    {
        return dirname($this->document->getFile()->getRealPath()) . substr($link, 1);
    }

    private function resolveMedia(array $matches, string $link): string
    {
        $key = 'MEDIAITEM' . count($this->media);

        $image = dirname($this->document->getFile()->getRealPath()) . substr($link, 1);

        if (!file_exists($image)) {
            throw new \RuntimeException(sprintf('Unable to find and therefore link %s on %s', $image, $this->document->getFile()->getRelativePathname()));
        }

        $this->media[$key] = $image;

        return str_replace($matches[1], $key, $matches[0]);
    }

//    public function replaceLinkToMedia(array &$metadata, array $matches, string $file, string $referencedFile): string
//    {
//        if (!key_exists('media', $metadata[$file])) {
//            $metadata[$file]['media'] = [];
//        }
//
//        $mediaId = count($metadata[$file]['media']);
//        $mediaStub = '__MEDIAITEM' . $mediaId;
//        $metadata[$file]['media'][$mediaStub] = $referencedFile;
//
//        return str_replace($matches[1], $mediaStub, $matches[0]);
//    }
//
//    public function replaceLinkToMarkdown(array $matches, string $file, array $metadata, string $linkAnchor, $referencedFile): string
//    {
//        // If the link contains a anchor, correct it
//        if ($linkAnchor !== '') {
//            // If a #-anchor contains a dot '.', replace it with a dash '-'
//            // Also convert the string to lowercase, as this is the convention for anchors
//            $linkAnchor = $this->fixLinkAnchors($linkAnchor);
//        }
//
//        if ($referencedFile !== false && is_file($referencedFile)) {
//            // If the link is another markdown file, check if we previously extracted a wikiUrl from it
//            if (!key_exists($referencedFile, $metadata) || !key_exists(self::WIKI_URL_TAG, $metadata[$referencedFile])) {
//                $this->errorStack[]
//                    = vsprintf('Unable to resolve wikiUrl for the file %s referenced in %s. The resulting link will be broken.', [$referencedFile, $file]);
//                // Return the original href tag
//                return $matches[0];
//            }
//
//            // Resolve the markdown filepath and append the anchor if there is any
//            $resolvedLink = $metadata[$referencedFile][self::WIKI_URL_TAG] . $linkAnchor;
//
//            return str_replace($matches[1], $resolvedLink, $matches[0]);
//        }
//
//        $this->warningStack[] = vsprintf('The markdownfile "%s" referenced in "%s" does not exist !', [$matches[1], $file]);
//
//        // If there is no filename preceding the anchor, just return the anchor
//        if ($linkAnchor !== '') {
//            return str_replace($matches[1], $linkAnchor, $matches[0]);
//        }
//
//        // Nothing to be done
//        return $matches[0];
//    }

    private function fixLinkAnchors(string $anchor): string
    {
        return '#' . strtolower(str_replace('.', '-', $anchor));
    }

    /**
     * @param $linkHref
     *
     * @return mixed
     */
    private function resolveLinkUrl(array $matches, DocumentTree $tree, $linkHref, string $linkAnchor): string
    {
        $absolutePath = $this->toAbsolutePath($linkHref);

        try {
            $relatedDocument = $tree->findByAbsolutePath($absolutePath);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf('No file found named %s referenced from %s', $absolutePath, $this->document->getFile()->getRealPath()));
        }

        $url = $relatedDocument->getMetadata()->getPrefixedUrlEn();

        if ($linkAnchor) {
            return str_replace($matches[1], $url . $this->fixLinkAnchors($linkAnchor), $matches[0]);
        }

        return str_replace($matches[1], $url, $matches[0]);
    }
}
