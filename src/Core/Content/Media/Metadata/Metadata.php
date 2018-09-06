<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata;

use Shopware\Core\Content\Media\Metadata\Type\MetadataType;
use Shopware\Core\Framework\Struct\Struct;

class Metadata extends Struct
{
    /**
     * @var array
     */
    protected $rawMetadata = [];

    /**
     * @var string|null
     */
    protected $typeName;

    /**
     * @var MetadataType|null
     */
    protected $type;

    public function getTypeName(): ?string
    {
        return $this->typeName;
    }

    public function getType(): ?MetadataType
    {
        return $this->type;
    }

    public function setType(?MetadataType $type): void
    {
        $this->type = $type;
        $this->typeName = null;

        if ($type) {
            $this->typeName = $type->getName();
        }
    }

    public function setRawMetadata(array $rawMetadata): void
    {
        $this->rawMetadata = $rawMetadata;
    }

    public function getRawMetadata(): array
    {
        return $this->rawMetadata;
    }
}
