<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class ProductDescriptionReviewsTemplateTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('alignmentProvider')]
    public function testAlignmentWrapperIsBalanced(?string $alignment, ?string $verticalAlign, bool $hasWrapper): void
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $template = $twig->createTemplate(<<<'TWIG'
            {% extends '@Storefront/storefront/element/cms-element-product-description-reviews.html.twig' %}
            {% block element_product_description_reviews_tabs_navigation %}{% endblock %}
            {% block element_product_description_reviews_tabs_content %}{% endblock %}
            TWIG);

        $html = $template->render([
            'element' => [
                'type' => 'product-description-reviews',
                'fieldConfig' => ['elements' => [
                    'alignment' => ['value' => $alignment],
                    'verticalAlign' => ['value' => $verticalAlign],
                ]],
                'data' => ['product' => ['id' => 'product-id']],
            ],
        ]);

        static::assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
        static::assertSame($hasWrapper, str_contains($html, 'class="cms-element-alignment'));
    }

    /**
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function alignmentProvider(): iterable
    {
        yield 'alignment enabled without legacy verticalAlign' => ['center', null, true];
        yield 'alignment disabled despite legacy verticalAlign' => [null, 'center', false];
    }
}
