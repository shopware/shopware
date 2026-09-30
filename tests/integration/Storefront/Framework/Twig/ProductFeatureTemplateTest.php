<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class ProductFeatureTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testTextCustomFieldIsRendered(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::TEXT, ['content' => 'wood']);

        static::assertStringContainsString('Material:', $output);
        static::assertStringContainsString('wood', $output);
    }

    public function testSelectCustomFieldRendersTheResolvedLabels(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::SELECT, [
            'content' => ['oak', 'pine'],
            'display' => ['Oak', 'Pine'],
        ]);

        static::assertStringContainsString('Material:', $output);
        static::assertStringContainsString('Oak, Pine', $output);
    }

    public function testPriceCustomFieldRendersTheResolvedCurrency(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::PRICE, [
            'content' => [['currencyId' => Uuid::randomHex(), 'net' => 10.0, 'gross' => 11.9, 'linked' => true]],
            'display' => 11.9,
        ]);

        static::assertStringContainsString('Material:', $output);
        static::assertStringContainsString('11.9', $output);
    }

    /**
     * Line items of orders placed before select, entity and price custom fields were resolved carry
     * no display value, so there is nothing to render for them.
     */
    public function testSelectCustomFieldWithoutADisplayValueIsNotRendered(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::SELECT, ['content' => ['oak']]);

        static::assertStringNotContainsString('product-feature-list-item', $output);
    }

    public function testUnsupportedCustomFieldTypeIsNotRendered(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::JSON, ['content' => ['foo' => 'bar']]);

        static::assertStringNotContainsString('product-feature-list-item', $output);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function renderFeature(string $type, array $value): string
    {
        $context = $this->createSalesChannelContext();

        return $this->renderInStorefrontRequest('@Storefront/storefront/component/product/feature/list.html.twig', [
            'context' => $context,
            'lineItem' => new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE),
            'features' => [
                [
                    'label' => 'Material',
                    'value' => [...['id' => Uuid::randomHex(), 'type' => $type], ...$value],
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD,
                ],
            ],
        ], $context);
    }

    /**
     * Renders inside a Storefront request, so `TemplateDataExtension` resolves real globals instead of caching
     * them empty in the shared environment, and resets them afterwards so later tests resolve their own.
     *
     * @param array<string, mixed> $parameters
     */
    private function renderInStorefrontRequest(string $template, array $parameters, SalesChannelContext $context): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);
        $requestStack = static::getContainer()->get(RequestStack::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $requestStack->push($request);

        try {
            return $twig->render($template, $parameters);
        } finally {
            $requestStack->pop();
            $twig->resetGlobals();
        }
    }
}
