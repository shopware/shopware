<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CmsPageTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected ?string $name = null;

    protected string $cmsPageId;

    protected string $cmsPageVersionId;

    protected ?CmsPageEntity $cmsPage = null;

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

    public function setName(?string $name): void
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

    public function getCmsPageVersionId(): string
    {
        return $this->cmsPageVersionId;
    }

    public function setCmsPageVersionId(string $cmsPageVersionId): void
    {
        $this->cmsPageVersionId = $cmsPageVersionId;
    }
}
