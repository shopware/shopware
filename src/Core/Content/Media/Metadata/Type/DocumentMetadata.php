<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

class DocumentMetadata extends MetadataType
{
    /**
     * @var string|null
     */
    protected $pages;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $creator;

    public static function getValidFileExtensions(): array
    {
        return [
            'pdf',
            'doc',
            'docx',
        ];
    }

    public static function create(): MetadataType
    {
        return new self();
    }

    public function getName(): string
    {
        return 'document';
    }

    public function getPages(): ?int
    {
        return $this->pages;
    }

    public function setPages(int $pages): void
    {
        $this->pages = $pages;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCreator(): ?string
    {
        return $this->creator;
    }

    public function setCreator(string $creator): void
    {
        $this->creator = $creator;
    }
}
