<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Record implements \JsonSerializable
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
    protected $extensions = [];

    /**
     * @var array
     */
    protected $links = [];

    /**
     * @var array[]
     */
    protected $relationships = [];

    /**
     * @var array
     */
    protected $meta;

    public function __construct(
        string $id = '',
        string $type = ''
    ) {
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

    /**
     * @return array[]
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function addLink(string $key, string $link): void
    {
        $this->links[$key] = $link;
    }

    public function getLink(string $key): string
    {
        return $this->links[$key];
    }

    public function addRelationship(string $key, array $relationship): void
    {
        $this->relationships[$key] = $relationship;
    }

    public function addMeta(string $key, $data): void
    {
        $this->meta[$key] = $data;
    }

    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);

        unset($vars['extensions']);
        foreach ($vars['relationships'] as $i => $_x) {
            unset($vars['relationships'][$i]['tmp']);
        }

        // if links are empty it should be decoded as empty object instead of empty array: https://jsonapi.org/format/#document-links
        if ((is_countable($vars['links']) ? \count($vars['links']) : 0) === 0) {
            $vars['links'] = new \stdClass();
        }

        // if attributes are empty it should be decoded as empty object instead of empty array: https://jsonapi.org/format/#document-resource-object-attributes
        if ((is_countable($vars['attributes']) ? \count($vars['attributes']) : 0) === 0) {
            $vars['attributes'] = new \stdClass();
        }

        return $vars;
    }

    public function addExtension(string $key, $value): void
    {
        $this->extensions[$key] = $value;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function merge(Entity $entity): void
    {
        $this->id = $entity->getUniqueIdentifier();

        $data = $entity->jsonSerialize();

        foreach ($this->attributes as $key => $_relationship) {
            $this->attributes[$key] = $data[$key] ?? null;
        }

        // Force customFields to be an object when it's empty
        if (isset($this->attributes['customFields']) && $this->attributes['customFields'] === []) {
            $this->attributes['customFields'] = new \stdClass();
        }

        if (isset($this->attributes['translated']['customFields']) && $this->attributes['translated']['customFields'] === []) {
            $this->attributes['translated']['customFields'] = new \stdClass();
        }

        if ($entity->hasExtension('foreignKeys')) {
            $extension = $entity->getExtension('foreignKeys')
                ->jsonSerialize();

            unset($extension['extensions']);

            foreach ($extension as $property => $value) {
                if (\array_key_exists($property, $this->attributes)) {
                    continue;
                }
                $this->attributes[$property] = $value;
            }
        }

        foreach ($this->relationships as $key => &$relationship) {
            /** @var Entity|EntityCollection<Entity>|null $relationData */
            $relationData = $data[$key] ?? null;

            if ($relationData === null) {
                continue;
            }

            $entityName = $relationship['tmp']['definition']->getEntityName();

            if ($relationData instanceof EntityCollection) {
                $relationship['data'] = [];

                foreach ($relationData as $item) {
                    $relationship['data'][] = [
                        'type' => $entityName,
                        'id' => $item->getUniqueIdentifier(),
                    ];
                }
            } else {
                $relationship['data'] = [
                    'type' => $entityName,
                    'id' => $relationData->getUniqueIdentifier(),
                ];
            }
        }
    }

    public function setRelationships(array $relationships): void
    {
        $this->relationships = $relationships;
    }
}
