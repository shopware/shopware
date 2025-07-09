<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Installer;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(Installer::class)]
class InstallerTest extends TestCase
{
    private ContainerBuilder $container;

    /**
     * @var array<string, array{currency:string}>
     */
    private array $preselection;

    /**
     * @var array<string, string>
     */
    private array $currencies;

    /**
     * @var array<string, array{id:string, label:string}>
     */
    private array $supportedLanguages;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->registerExtension(new FrameworkExtension());

        $installer = new Installer();
        $installer->build($this->container);

        $preselection = $this->container->getParameter('shopware.installer.configurationPreselection');
        static::assertIsArray($preselection);
        static::assertArrayHasKey('de', $preselection);
        $germanPreselection = $preselection['de'];
        static::assertArrayHasKey('currency', $germanPreselection);
        $this->preselection = $preselection;

        $currencies = $this->container->getParameter('shopware.installer.supportedCurrencies');
        static::assertIsArray($currencies);
        static::assertArrayHasKey('EUR', $currencies);
        $euroCurrency = $currencies['EUR'];
        static::assertSame('EUR', $euroCurrency);
        $this->currencies = $currencies;

        $supportedLanguages = $this->container->getParameter('shopware.installer.supportedLanguages');
        static::assertIsArray($supportedLanguages);
        static::assertArrayHasKey('de', $supportedLanguages);
        $germanLanguage = $supportedLanguages['de'];
        static::assertArrayHasKey('id', $germanLanguage);
        static::assertArrayHasKey('label', $germanLanguage);
        $this->supportedLanguages = $supportedLanguages;
    }

    public function testSupportedLanguages(): void
    {
        static::assertSame(
            [
                'cs' => ['id' => 'cs-CZ', 'label' => 'Český'],
                'da-DK' => ['id' => 'da-DK', 'label' => 'Dansk'],
                'de' => ['id' => 'de-DE', 'label' => 'Deutsch'],
                'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
                'es-ES' => ['id' => 'es-ES', 'label' => 'Español'],
                'fr' => ['id' => 'fr-FR', 'label' => 'Français'],
                'it' => ['id' => 'it-IT', 'label' => 'Italiano'],
                'nl' => ['id' => 'nl-NL', 'label' => 'Nederlands'],
                'no' => ['id' => 'no-NO', 'label' => 'Norsk'],
                'pl' => ['id' => 'pl-PL', 'label' => 'Język polski'],
                'pt-PT' => ['id' => 'pt-PT', 'label' => 'Português'],
                'sv-SE' => ['id' => 'sv-SE', 'label' => 'Svenska'],
            ],
            $this->supportedLanguages
        );
    }

    public function testSupportedCurrencies(): void
    {
        static::assertSame(
            [
                'EUR' => 'EUR',
                'USD' => 'USD',
                'GBP' => 'GBP',
                'PLN' => 'PLN',
                'CHF' => 'CHF',
                'SEK' => 'SEK',
                'DKK' => 'DKK',
                'NOK' => 'NOK',
                'CZK' => 'CZK',
            ],
            $this->currencies
        );
    }

    public function testConfigurationPreselection(): void
    {
        static::assertSame(
            [
                'cs' => ['currency' => 'CZK'],
                'da-DK' => ['currency' => 'DKK'],
                'de' => ['currency' => 'EUR'],
                'en-US' => ['currency' => 'USD'],
                'en' => ['currency' => 'GBP'],
                'es-ES' => ['currency' => 'EUR'],
                'fr' => ['currency' => 'EUR'],
                'it' => ['currency' => 'EUR'],
                'nl' => ['currency' => 'EUR'],
                'no' => ['currency' => 'NOK'],
                'pl' => ['currency' => 'PLN'],
                'pt-PT' => ['currency' => 'EUR'],
                'sv-SE' => ['currency' => 'SEK'],
            ],
            $this->preselection
        );
    }

    public function testLanguageHasPreselection(): void
    {
        foreach ($this->supportedLanguages as $iso => $language) {
            static::assertArrayHasKey($iso, $this->preselection, \sprintf('Language "%s" does not have a preselection', $iso));
            static::assertArrayHasKey('currency', $this->preselection[$iso], \sprintf('Language "%s" does not have a currency preselection', $iso));
            static::assertNotEmpty($this->preselection[$iso]['currency'], \sprintf('Language "%s" has an empty currency preselection', $iso));
        }
    }
}
