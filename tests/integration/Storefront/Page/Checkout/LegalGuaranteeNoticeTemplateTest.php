<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\Checkout;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
#[Package('checkout')]
class LegalGuaranteeNoticeTemplateTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use EnvTestBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;

    private SystemConfigService $systemConfig;

    private Environment $twig;

    protected function setUp(): void
    {
        $this->systemConfig = static::getContainer()->get(SystemConfigService::class);
        $this->systemConfig->set('core.cart.showLegalGuaranteeNotice', true);
        $this->systemConfig->set('core.cart.showLegalGuaranteeNoticeInline', false);
        $this->systemConfig->set('core.basicInformation.tosPage', Uuid::randomHex());
        $this->systemConfig->set('core.basicInformation.revocationPage', Uuid::randomHex());

        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);
        $this->twig = $twig;

        static::getContainer()->get(RequestStack::class)->push(Request::create('/checkout/confirm'));
    }

    #[DataProvider('displayModes')]
    public function testDisabledNoticeHidesBothDisplayModes(bool $accessibilityTweaks, bool $showInline): void
    {
        $this->setEnvVars(['ACCESSIBILITY_TWEAKS' => $accessibilityTweaks]);
        $this->systemConfig->set('core.cart.showLegalGuaranteeNotice', false);
        $this->systemConfig->set('core.cart.showLegalGuaranteeNoticeInline', $showInline);

        $page = new Crawler($this->renderCheckout());

        static::assertCount(0, $page->filter('.confirm-legal-guarantee-notice-inline'));
        static::assertCount(0, $page->filter('#legalGuaranteeNoticeModal'));
        static::assertCount(0, $page->filter('[data-bs-target="#legalGuaranteeNoticeModal"]'));
        static::assertCount(0, $page->filter('a[href="#legalGuaranteeNoticeInline"]'));
        static::assertCount(1, $page->filter('#tos[required][form="confirmOrderForm"]'));
    }

    #[DataProvider('accessibilitySettings')]
    public function testDefaultDisplayKeepsTheNoticeInAModal(bool $accessibilityTweaks): void
    {
        $this->setEnvVars(['ACCESSIBILITY_TWEAKS' => $accessibilityTweaks]);
        $this->systemConfig->delete('core.cart.showLegalGuaranteeNoticeInline');

        $page = new Crawler($this->renderCheckout());

        static::assertCount(0, $page->filter('.confirm-legal-guarantee-notice-inline'));
        static::assertCount(1, $page->filter('#tos[required][form="confirmOrderForm"]'));
        static::assertCount(1, $page->filter('[data-bs-target="#legalGuaranteeNoticeModal"]'));
        static::assertCount(1, $page->filter('#legalGuaranteeNoticeModal svg'));
        static::assertSame('https://europa.eu/youreurope/guarantees', $page->filter('#legalGuaranteeNoticeModal .modal-body a')->attr('href'));
    }

    #[DataProvider('accessibilitySettings')]
    public function testInlineNoticeAppearsBelowTheRequiredTermsCheckbox(bool $accessibilityTweaks): void
    {
        $this->setEnvVars(['ACCESSIBILITY_TWEAKS' => $accessibilityTweaks]);
        $this->systemConfig->set('core.cart.showLegalGuaranteeNoticeInline', true);

        $html = $this->renderCheckout();
        $page = new Crawler($html);

        static::assertCount(1, $page->filter('#tos[required][form="confirmOrderForm"]'));
        static::assertCount(1, $page->filter('label[for="tos"] ' . ($accessibilityTweaks ? 'button' : 'a') . '[data-ajax-modal]'));
        static::assertCount(1, $page->filter('svg'));
        static::assertCount(1, $page->filter('.confirm-legal-guarantee-notice-inline svg'));
        static::assertCount(1, $page->filter('a[href="#legalGuaranteeNoticeInline"]'));
        static::assertCount(1, $page->filter('#legalGuaranteeNoticeInline svg'));
        static::assertCount(0, $page->filter('#legalGuaranteeNoticeModal'));
        static::assertCount(1, $page->filterXPath('//label[@for="tos"]/following::div[contains(concat(" ", normalize-space(@class), " "), " confirm-legal-guarantee-notice-inline ")]'));
        static::assertStringContainsString(trim($this->twig->render('@Content/legal-guarantee-notice/en.svg')), $html);
    }

    #[DataProvider('displayModes')]
    public function testNoticePreservesTheRequiredRevocationCheckbox(bool $accessibilityTweaks, bool $showInline): void
    {
        $this->setEnvVars(['ACCESSIBILITY_TWEAKS' => $accessibilityTweaks]);
        $this->systemConfig->set('core.cart.showLegalGuaranteeNoticeInline', $showInline);

        $page = new Crawler($this->renderCheckout(showRevocation: true));

        static::assertCount(1, $page->filter('#tos[required][form="confirmOrderForm"]'));
        static::assertCount(1, $page->filter('#revocation[required][form="confirmOrderForm"]'));
        static::assertCount(1, $page->filter('label[for="revocation"]'));
        static::assertCount(1, $page->filter('svg'));
    }

    #[DataProvider('accessibilitySettings')]
    public function testInlineNoticeUsesTheCheckoutLanguageAndOfficialLink(bool $accessibilityTweaks): void
    {
        $this->setEnvVars(['ACCESSIBILITY_TWEAKS' => $accessibilityTweaks]);
        $languageId = Uuid::randomHex();
        $localeId = Uuid::randomHex();
        static::getContainer()->get('language.repository')->create([[
            'id' => $languageId,
            'name' => 'Legal guarantee German',
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'locale' => [
                'id' => $localeId,
                'name' => 'Legal guarantee German',
                'territory' => 'Germany',
                'code' => 'de-DE-notice-test',
            ],
            'translationCodeId' => $localeId,
        ]], Context::createDefaultContext());
        static::getContainer()->get(LanguageLocaleCodeProvider::class)->reset();
        $this->systemConfig->set('core.cart.showLegalGuaranteeNoticeInline', true);

        $html = $this->renderCheckout($languageId);
        $notice = (new Crawler($html))->filter('.confirm-legal-guarantee-notice-inline');

        static::assertCount(1, $notice->filter('svg'));
        static::assertStringContainsString(trim($this->twig->render('@Content/legal-guarantee-notice/de.svg')), $html);
        static::assertSame('https://europa.eu/youreurope/garantien', $notice->filter('a')->attr('href'));
    }

    /**
     * @return \Generator<string, array{bool}>
     */
    public static function accessibilitySettings(): \Generator
    {
        yield 'legacy modal links' => [false];
        yield 'accessible modal buttons' => [true];
    }

    /**
     * @return \Generator<string, array{bool, bool}>
     */
    public static function displayModes(): \Generator
    {
        yield 'legacy checkout with modal notice' => [false, false];
        yield 'legacy checkout with inline notice' => [false, true];
        yield 'accessible checkout with modal notice' => [true, false];
        yield 'accessible checkout with inline notice' => [true, true];
    }

    private function renderCheckout(string $languageId = Defaults::LANGUAGE_SYSTEM, bool $showRevocation = false): string
    {
        $page = new CheckoutConfirmPage();
        $page->setCart(new Cart(Uuid::randomHex()));
        $page->setShowRevocation($showRevocation);

        return $this->twig->createTemplate(<<<'TWIG'
            {% sw_extends '@Storefront/storefront/page/checkout/confirm/index.html.twig' %}
            {% block base_head %}{% endblock %}
            {% block base_body %}
                {{ block('page_checkout_confirm_tos') }}
                {{ block('page_checkout_aside_actions') }}
            {% endblock %}
            TWIG)->render([
            'context' => ['languageId' => $languageId],
            'page' => $page,
        ]);
    }
}
