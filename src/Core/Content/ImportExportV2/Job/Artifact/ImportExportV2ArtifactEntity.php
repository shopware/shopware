<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Artifact;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportV2ArtifactEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $mimeType;

    protected string $contents;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getContents(): string
    {
        return $this->contents;
    }

    public function setContents(string $contents): void
    {
        $this->contents = $contents;
    }
}
