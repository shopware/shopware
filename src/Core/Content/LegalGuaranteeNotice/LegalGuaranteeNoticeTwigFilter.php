<?php declare(strict_types=1);

namespace Shopware\Core\Content\LegalGuaranteeNotice;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('inventory')]
class LegalGuaranteeNoticeTwigFilter extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly LegalGuaranteeNoticeRenderer $renderer)
    {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_legal_guarantee_notice', $this->render(...), ['is_safe' => ['html']]),
            new TwigFilter('sw_legal_guarantee_notice_link', $this->link(...)),
        ];
    }

    public function render(string $languageId): string
    {
        return $this->renderer->renderForLanguage($languageId);
    }

    public function link(string $languageId): string
    {
        return $this->renderer->linkForLanguage($languageId);
    }
}
