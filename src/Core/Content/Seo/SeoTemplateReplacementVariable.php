<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

class SeoTemplateReplacementVariable
{
    /**
     * @var string
     */
    private $mappedEntityName;

    /**
     * @var string|null
     */
    private $mappedEntityFields;

    public function __construct(string $mappedEntityName, ?string $mappedEntityFields = null)
    {
        $this->mappedEntityName = $mappedEntityName;
        $this->mappedEntityFields = $mappedEntityFields;
    }

    public function hasMappedFields(): bool
    {
        return $this->mappedEntityFields !== null;
    }

    public function getMappedEntityName(): string
    {
        return $this->mappedEntityName;
    }

    public function getMappedEntityFields(): ?string
    {
        return $this->mappedEntityFields;
    }
}
