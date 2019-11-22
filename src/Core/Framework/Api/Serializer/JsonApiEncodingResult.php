<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

class JsonApiEncodingResult implements \JsonSerializable
{
    /**
     * @var Record[]
     */
    protected $data = [];

    /**
     * @var Record[]
     */
    protected $included = [];

    /**
     * @var array
     */
    protected $keyCollection = [];

    /**
     * @var bool
     */
    protected $single = false;

    /**
     * @var array
     */
    protected $metaData = [];

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var int
     */
    private $apiVersion;

    public function __construct(string $baseUrl, int $apiVersion)
    {
        $this->baseUrl = $baseUrl;
        $this->apiVersion = $apiVersion;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getIncluded(): array
    {
        return $this->included;
    }

    public function addEntity(Record $entity): void
    {
        $key = $entity->getId() . '-' . $entity->getType();

        $this->data[$key] = $entity;

        if (isset($this->included[$key])) {
            unset($this->included[$key]);
        }

        $this->keyCollection[$key] = 1;
    }

    public function addIncluded(Record $entity): void
    {
        $key = $entity->getId() . '-' . $entity->getType();

        if ($this->contains($entity->getId(), $entity->getType())) {
            return;
        }

        $this->included[$key] = $entity;

        $this->keyCollection[$key] = 1;
    }

    public function contains(string $id, string $type): bool
    {
        $key = $id . '-' . $type;

        return isset($this->keyCollection[$key]);
    }

    public function containsInIncluded(string $id, string $type): bool
    {
        $key = $id . '-' . $type;

        return isset($this->included[$key]);
    }

    public function containsInData(string $id, string $type): bool
    {
        $key = $id . '-' . $type;

        return isset($this->data[$key]);
    }

    public function jsonSerialize()
    {
        $output = [
            'data' => $this->isSingle() ? array_shift($this->data) : array_values($this->data),
            'included' => array_values($this->included),
        ];

        if (!empty($this->metaData)) {
            $output = array_merge($output, $this->metaData);
        }

        return $output;
    }

    public function isSingle(): bool
    {
        return $this->single;
    }

    public function setSingleResult(bool $single): void
    {
        $this->single = $single;
    }

    public function setMetaData(array $metaData): void
    {
        $this->metaData = $metaData;
    }

    public function getMetaData(): array
    {
        return $this->metaData;
    }

    public function getApiVersion(): int
    {
        return $this->apiVersion;
    }
}
