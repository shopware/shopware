<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

class SerializedEntity implements \JsonSerializable
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $links = [];

    /**
     * @var array
     */
    protected $relationships = [];

    public function __construct(string $id, string $type)
    {
        $this->id = $id;
        $this->type = $type;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getLinks(): array
    {
        return $this->links;
    }

    public function getRelationships(): array
    {
        return $this->relationships;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function addAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function addLink(string $key, string $link): void
    {
        $this->links[$key] = $link;
    }

    public function addRelationship(string $key, array $relationship): void
    {
        $this->relationships[$key] = $relationship;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    public function createAttribute(string $key, $value): void
    {
        if (array_key_exists($key, $this->attributes)) {
            return;
        }
        $this->attributes[$key] = $value;
    }

    public function addExtension(string $key, $value): void
    {
        $this->attributes['extensions'][$key] = $value;
    }
}
