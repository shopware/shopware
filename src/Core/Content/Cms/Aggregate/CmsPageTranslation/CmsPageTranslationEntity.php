<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class CmsPageTranslationEntity extends TranslationEntity
{
    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string
     */
    protected $cmsPageId;

    /**
     * @var CmsPageEntity|null
     */
    protected $cmsPage;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getCmsPageId(): string
    {
        return $this->cmsPageId;
    }

    public function setCmsPageId(string $cmsPageId): void
    {
        $this->cmsPageId = $cmsPageId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getApiAlias(): string
    {
        return 'cms_page_translation';
    }
}
