<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LegalGuaranteeNotice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer;
use Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeTwigFilter;
use Shopware\Core\Framework\Log\Package;
use Twig\TwigFilter;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(LegalGuaranteeNoticeTwigFilter::class)]
class LegalGuaranteeNoticeTwigFilterTest extends TestCase
{
    public function testGetFiltersRegistersBothFilters(): void
    {
        $renderer = static::createStub(LegalGuaranteeNoticeRenderer::class);
        $filter = new LegalGuaranteeNoticeTwigFilter($renderer);

        $filters = $filter->getFilters();

        static::assertCount(2, $filters);
        static::assertContainsOnlyInstancesOf(TwigFilter::class, $filters);

        $names = array_map(static fn (TwigFilter $f) => $f->getName(), $filters);
        static::assertContains('sw_legal_guarantee_notice', $names);
        static::assertContains('sw_legal_guarantee_notice_link', $names);
    }

    public function testRenderDelegatesToRenderer(): void
    {
        $renderer = $this->createMock(LegalGuaranteeNoticeRenderer::class);
        $renderer->expects($this->once())
            ->method('renderForLanguage')
            ->with('language-id')
            ->willReturn('<svg>notice</svg>');

        $filter = new LegalGuaranteeNoticeTwigFilter($renderer);

        static::assertSame('<svg>notice</svg>', $filter->render('language-id'));
    }

    public function testLinkDelegatesToRenderer(): void
    {
        $renderer = $this->createMock(LegalGuaranteeNoticeRenderer::class);
        $renderer->expects($this->once())
            ->method('linkForLanguage')
            ->with('language-id')
            ->willReturn('https://europa.eu/youreurope/garantien');

        $filter = new LegalGuaranteeNoticeTwigFilter($renderer);

        static::assertSame('https://europa.eu/youreurope/garantien', $filter->link('language-id'));
    }
}
