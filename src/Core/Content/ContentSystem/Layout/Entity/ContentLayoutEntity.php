<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Entity;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class ContentLayoutEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $version;

    /**
     * @var array<ContentElement>
     */
    protected array $layout;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $schema = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @return array<ContentElement>
     */
    public function getLayout(): array
    {
        return $this->layout;
    }

    /**
     * @param array<ContentElement> $layout
     */
    public function setLayout(array $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSchema(): ?array
    {
        return $this->schema;
    }

    /**
     * @param array<string, mixed>|null $schema
     */
    public function setSchema(?array $schema): void
    {
        $this->schema = $schema;
    }
}
