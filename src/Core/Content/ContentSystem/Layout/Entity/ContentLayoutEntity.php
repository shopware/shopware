<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Entity;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ContentLayoutEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $version;

    /**
     * @var list<ContentElement>
     */
    protected array $layout;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $schema = null;

    /**
     * @api
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @api
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @api
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @api
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @api
     *
     * @return list<ContentElement>
     */
    public function getLayout(): array
    {
        return $this->layout;
    }

    /**
     * @api
     *
     * @param list<ContentElement> $layout
     */
    public function setLayout(array $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * @api
     *
     * @return array<string, mixed>|null
     */
    public function getSchema(): ?array
    {
        return $this->schema;
    }

    /**
     * @api
     *
     * @param array<string, mixed>|null $schema
     */
    public function setSchema(?array $schema): void
    {
        $this->schema = $schema;
    }
}
