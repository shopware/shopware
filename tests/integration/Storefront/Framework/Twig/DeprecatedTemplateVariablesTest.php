<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class DeprecatedTemplateVariablesTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('deprecatedVariableTemplates')]
    public function testDeprecatedVariablesAreOnlyAvailableBeforeV68(string $template, array $context): void
    {
        $output = $this->getTwig()->createTemplate($template)->render($context);

        static::assertStringContainsString(
            Feature::isActive('v6.8.0.0') ? 'legacy-variable-removed' : 'legacy-variable-available',
            $output,
        );
    }

    public function testDeprecatedAnalyticsGlobalsAreOnlyRenderedBeforeV68(): void
    {
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setActive(true);
        $analytics->setTrackingId('G-TEST');
        $analytics->setTrackOrders(false);
        $analytics->setTrackOffcanvasCart(false);
        $analytics->setAnonymizeIp(true);

        $output = $this->getTwig()->render('@Storefront/storefront/component/analytics.html.twig', [
            'storefrontAnalytics' => $analytics,
            'controllerName' => 'checkout',
            'controllerAction' => 'confirmPage',
            'activeRoute' => 'frontend.checkout.confirm.page',
        ]);

        if (Feature::isActive('v6.8.0.0')) {
            static::assertStringNotContainsString('window.controllerName', $output);
            static::assertStringNotContainsString('window.actionName', $output);
        } else {
            static::assertStringContainsString('window.controllerName', $output);
            static::assertStringContainsString('window.actionName', $output);
        }

        static::assertStringContainsString('window.activeRoute', $output);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function deprecatedVariableTemplates(): iterable
    {
        $order = [
            'billingAddress' => ['id' => 'billing-address'],
            'primaryOrderDelivery' => [
                'shippingOrderAddressId' => 'billing-address',
                'shippingOrderAddress' => ['id' => 'shipping-address'],
            ],
            'deliveries' => ['elements' => []],
        ];

        yield 'checkout confirm deliveries' => [
            <<<'TWIG'
                {% sw_extends '@Storefront/storefront/page/checkout/confirm/confirm-address.html.twig' %}

                {% block page_checkout_confirm_address %}
                    {{ deliveries is defined ? 'legacy-variable-available' : 'legacy-variable-removed' }}
                {% endblock %}
                TWIG,
            ['page' => ['order' => $order]],
        ];

        yield 'checkout finish deliveries' => [
            <<<'TWIG'
                {% sw_extends '@Storefront/storefront/page/checkout/finish/finish-address.html.twig' %}

                {% block page_checkout_finish_address_shipping %}
                    {{ deliveries is defined ? 'legacy-variable-available' : 'legacy-variable-removed' }}
                {% endblock %}

                {% block page_checkout_finish_address_billing %}{% endblock %}
                TWIG,
            ['page' => ['order' => $order]],
        ];

        yield 'navbar pathIdList' => [
            <<<'TWIG'
                {% sw_extends '@Storefront/storefront/layout/navbar/navbar.html.twig' %}

                {% block layout_navbar_nav_element %}
                    {{ navbarOptions.pathIdList is defined ? 'legacy-variable-available' : 'legacy-variable-removed' }}
                {% endblock %}
                TWIG,
            ['shopware' => ['navigation' => ['pathIdList' => ['category-id']]]],
        ];
    }

    private function getTwig(): Environment
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
