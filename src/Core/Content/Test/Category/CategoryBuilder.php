<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category;

use Shopware\Core\Content\Test\Cms\LayoutBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Test\TestBuilderTrait;

/**
 * @internal
 * How to use:
 *
 * $x = (new CategoryBuilder(new IdsCollection(), 'c1'))
 *          ->layout('my-custom-layout')
 *          ->slot('my-slot', ['media' => ['source' => 'static', value => 'some-uuid']])
 *          ->build();
 */
class CategoryBuilder
{
    use TestBuilderTrait;

    public string $id;

    protected ?string $parentId = null;

    protected ?string $name;

    /**
     * @var array{id: string}|array<mixed>|null
     */
    protected ?array $cmsPage = null;

    /**
     * @var array<string, mixed>
     */
    protected array $customFields = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $translations = [];

    public function __construct(
        IdsCollection $ids,
        protected string $categoryName,
    ) {
        $this->ids = $ids;
        $this->id = $this->ids->create($categoryName);
        $this->name = $categoryName;
    }

    public function parent(string $key): self
    {
        $this->parentId = $this->ids->get($key);

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function customField(string $key, $value): self
    {
        $this->customFields[$key] = $value;

        return $this;
    }

    public function translation(string $languageId, string $key, mixed $value): self
    {
        $this->translations[$languageId][$key] = $value;

        return $this;
    }

    public function layout(string $key): self
    {
        if ($this->ids->has($key)) {
            $this->cmsPage = ['id' => $this->ids->get($key)];

            return $this;
        }

        $this->cmsPage = (new LayoutBuilder($this->ids, $key, 'categorypage'))
            ->build();

        return $this;
    }

    /**
     * @param array<mixed> $value
     */
    public function slot(string $key, array $value, string $languageId = Defaults::LANGUAGE_SYSTEM): self
    {
        if (isset($this->translations[$languageId]['slotConfig']) && \is_array($this->translations[$languageId]['slotConfig'])) {
            $slotConfig = $this->translations[$languageId]['slotConfig'];
        } else {
            $slotConfig = [];
        }

        $slotConfig[$this->ids->get($key)] = $value;

        $this->translation(
            $languageId,
            'slotConfig',
            $slotConfig
        );

        return $this;
    }
}
