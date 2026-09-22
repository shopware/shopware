<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
#[Package('checkout')]
class CheckoutLegalGuaranteeTemplateTest extends TestCase
{
    use EnvTestBehaviour;
    use IntegrationTestBehaviour;

    #[DataProvider('checkoutProvider')]
    public function testCheckoutSeparatesLegalGuaranteeFromTerms(bool $accessibilityTweaks, bool $showLegalGuaranteeNotice, string $locale): void
    {
        $this->setEnvVars(['ACCESSIBILITY_TWEAKS' => $accessibilityTweaks]);
        static::getContainer()->get(SystemConfigService::class)->set('core.cart.showLegalGuaranteeNotice', $showLegalGuaranteeNotice);

        $crawler = $this->render(<<<'TWIG'
            {% sw_extends '@Storefront/storefront/page/checkout/confirm/index.html.twig' %}
            {% block base_html %}<html>{% endblock %}
            {% block base_head %}{% endblock %}
            {% block base_body %}
                {{ block('page_checkout_confirm_tos') }}
                {{ block('page_checkout_confirm_legal_guarantee_notice') }}
            {% endblock %}
            TWIG, $locale);

        $terms = $crawler->filter('label[for="tos"]');
        static::assertCount(1, $terms);
        static::assertSame(
            $locale === 'de-DE' ? 'Ich habe die AGB gelesen und bin mit ihnen einverstanden.' : 'I have read and accepted the general terms and conditions.',
            $terms->text(),
        );
        static::assertCount(1, $terms->filter($accessibilityTweaks ? 'button[data-ajax-modal]' : 'a[data-ajax-modal]'));
        static::assertCount(0, $terms->filter('[data-bs-target="#legalGuaranteeNoticeModal"]'));
        static::assertCount(1, $crawler->filter('input#tos[required][form="confirmOrderForm"]'));

        $notice = $crawler->filter('.legal-guarantee-notice');
        static::assertCount($showLegalGuaranteeNotice ? 1 : 0, $notice);
        static::assertCount($showLegalGuaranteeNotice ? 1 : 0, $crawler->filter('#legalGuaranteeNoticeModal'));

        if ($showLegalGuaranteeNotice) {
            static::assertSame(
                $locale === 'de-DE' ? 'Bitte beachten Sie Ihre gesetzlichen Gewährleistungsrechte.' : 'Please note your legal guarantee rights.',
                $notice->text(),
            );
            static::assertCount(1, $notice->filter('button[type="button"][data-bs-target="#legalGuaranteeNoticeModal"]'));
            static::assertCount(0, $notice->filterXPath('ancestor::label'));
            static::assertCount(1, $crawler->filter('#legalGuaranteeNoticeModal .modal-body svg'));
        }
    }

    /**
     * @return iterable<string, array{bool, bool, string}>
     */
    public static function checkoutProvider(): iterable
    {
        foreach ([false, true] as $accessibilityTweaks) {
            foreach ([false, true] as $showLegalGuaranteeNotice) {
                foreach (['en-GB', 'de-DE'] as $locale) {
                    yield \sprintf('accessibility %d, guarantee %d, %s', $accessibilityTweaks, $showLegalGuaranteeNotice, $locale) => [$accessibilityTweaks, $showLegalGuaranteeNotice, $locale];
                }
            }
        }
    }

    #[DataProvider('privacyProvider')]
    public function testGermanRegistrationLabelsTermsLinkCorrectly(bool $accessibilityTweaks, bool $requireDataProtectionCheckbox): void
    {
        $this->setEnvVars(['ACCESSIBILITY_TWEAKS' => $accessibilityTweaks]);
        $config = static::getContainer()->get(SystemConfigService::class);
        $config->set('core.loginRegistration.requireDataProtectionCheckbox', $requireDataProtectionCheckbox);

        $tosPageId = Uuid::randomHex();
        $config->set('core.basicInformation.tosPage', $tosPageId);

        $crawler = $this->render(<<<'TWIG'
            {% sw_include '@Storefront/storefront/component/privacy-notice.html.twig' %}
            TWIG, 'de-DE');

        $termsLink = $crawler->filter(\sprintf('[data-url$="/%s"]', $tosPageId));
        static::assertCount(1, $termsLink);
        static::assertSame('AGB', $termsLink->text());
        static::assertSame($accessibilityTweaks ? 'button' : 'a', $termsLink->nodeName());
        static::assertStringNotContainsString('Gewährleistungsrechte', $crawler->text());
        static::assertCount($requireDataProtectionCheckbox ? 1 : 0, $crawler->filter('input[name="acceptedDataProtection"]'));
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function privacyProvider(): iterable
    {
        foreach ([false, true] as $accessibilityTweaks) {
            foreach ([false, true] as $requireDataProtectionCheckbox) {
                yield \sprintf('accessibility %d, checkbox %d', $accessibilityTweaks, $requireDataProtectionCheckbox) => [$accessibilityTweaks, $requireDataProtectionCheckbox];
            }
        }
    }

    private function render(string $template, string $locale): Crawler
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $translator = static::getContainer()->get(Translator::class);
        $previousLocale = $translator->getLocale();
        $translator->setLocale($locale);

        $requestStack = static::getContainer()->get(RequestStack::class);
        $requestStack->push(new Request());

        try {
            return new Crawler($twig->createTemplate($template)->render([
                'context' => Generator::generateSalesChannelContext(),
                'page' => new CheckoutConfirmPage(),
            ]));
        } finally {
            $requestStack->pop();
            $translator->setLocale($previousLocale);
        }
    }
}
