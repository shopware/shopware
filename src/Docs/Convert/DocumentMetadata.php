<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

use Cocur\Slugify\Slugify;

class DocumentMetadata
{
    private const INITIAL_VERSION = '6.0.0.0';

    private const META_TITLE_PREFIX = 'Shopware 6: ';

    /**
     * @var Document
     */
    private $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function getMetaTitleDe(): string
    {
        return self::META_TITLE_PREFIX . $this->requireMetadata('titleEn');
    }

    public function getMetaTitleEn(): string
    {
        return self::META_TITLE_PREFIX . $this->requireMetadata('titleEn');
    }

    public function getMetaDescriptionDe(): string
    {
        try {
            return $this->requireMetadata('metaDescriptionDe');
        } catch (\InvalidArgumentException $e) {
            return '';
        }
    }

    public function getMetaDescriptionEn(): string
    {
        try {
            return $this->requireMetadata('metaDescriptionEn');
        } catch (\InvalidArgumentException $e) {
            return '';
        }
    }

    public function getTitleDe(): string
    {
        return $this->requireMetadata('titleEn');
    }

    public function getTitleEn(): string
    {
        return $this->requireMetadata('titleEn');
    }

    public function getPrefixedUrlDe(): string
    {
        return '/de' . $this->document->getBaseUrl() . '-de' . $this->getUrl();
    }

    public function getPrefixedUrlEn(): string
    {
        return '/en' . $this->document->getBaseUrl() . '-en' . $this->getUrl();
    }

    public function getUrlDe(): string
    {
        return $this->document->getBaseUrl() . '-de' . $this->getUrl();
    }

    public function getUrlEn(): string
    {
        return $this->document->getBaseUrl() . '-en' . $this->getUrl();
    }

    public function getHash(): string
    {
        return $this->requireMetadata('hash');
    }

    public function isActive(): bool
    {
        try {
            return filter_var($this->requireMetadata('isActive'), \FILTER_VALIDATE_BOOLEAN);
        } catch (\InvalidArgumentException $e) {
            return true;
        }
    }

    public function toArray(DocumentTree $tree): array
    {
        $renderedDoc = $this->document->getHtml()->render($tree);

        return [
            'isCategory' => $this->document->isCategory(),
            'priority' => $this->document->getPriority(),
            'media' => $renderedDoc->getImages(),
            'locale' => [
                'de_DE' => [
                    'seoUrl' => $this->getPrefixedUrlDe(),
                    'searchableInAllLanguages' => false,
                ],
                'en_GB' => [
                    'seoUrl' => $this->getPrefixedUrlEn(),
                    'searchableInAllLanguages' => true,
                ],
            ],
            'version' => [
                'de_DE' => [
                    'title' => $this->requireMetadata('titleEn'),
                    'navigationTitle' => $this->requireMetadata('titleEn'),
                    'content' => '<p>Die Entwicklerdokumentation ist nur auf Englisch verfügbar.</p>',
                    'searchableInAllLanguages' => false,
                    'fromProductVersion' => self::INITIAL_VERSION,
                    'active' => $this->isActive(),
                    'metaTitle' => $this->getMetaTitleDe(),
                    'metaDescription' => $this->getMetaDescriptionDe(),
                ],
                'en_GB' => [
                    'title' => $this->requireMetadata('titleEn'),
                    'navigationTitle' => $this->requireMetadata('titleEn'),
                    'content' => $renderedDoc->getContents(),
                    'searchableInAllLanguages' => true,
                    'fromProductVersion' => self::INITIAL_VERSION,
                    'active' => $this->isActive(),
                    'metaTitle' => $this->getMetaTitleEn(),
                    'metaDescription' => $this->getMetaDescriptionEn(),
                ],
            ],
        ];
    }

    private function getUrl(): string
    {
        //walk the parent chain
        $sluggify = new Slugify();

        $urlParts = [];
        /** @var Document $document */
        foreach ($this->document->createParentChain() as $document) {
            $urlParts[] = $sluggify->slugify($document->getUrlPart());
        }

        return '/' . implode('/', $urlParts);
    }

    private function requireMetadata(string $key)
    {
        $metadata = $this->document->loadRawMetadata();

        if (!isset($metadata[$key])) {
            throw new \InvalidArgumentException(sprintf('Key %s on %s not found', $key, $this->document->getFile()->getRealPath()));
        }

        return $metadata[$key];
    }
}
