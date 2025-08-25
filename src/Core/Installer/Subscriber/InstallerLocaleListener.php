<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @internal
 *
 * @phpstan-import-type SupportedLanguages from \Shopware\Core\Installer\Controller\InstallerController
 */
#[Package('framework')]
class InstallerLocaleListener implements EventSubscriberInterface
{
    final public const FALLBACK_LOCALE = 'en';

    /**
     * @var array<string, string>
     */
    private readonly array $installerLanguages;

    /**
     * @param SupportedLanguages $installerLanguages
     */
    public function __construct(array $installerLanguages)
    {
        /* Map languages to lowercase keys for easier comparison against Symfony's request language detection.
         * Ensure that the fallback language is the first language, as Symfony's `getPreferredLanguage()` returns the
         * first array value if no accepted language matches any in the array */
        /** @var array<string, string> $mappedLanguages */
        $mappedLanguages = [
            self::FALLBACK_LOCALE => self::FALLBACK_LOCALE,
        ];
        foreach ($installerLanguages as $language => $installerLanguage) {
            $mappedLanguages[mb_strtolower($language)] = $language;
            $mappedLanguages[mb_strtolower($installerLanguage['id'])] = $language;
        }
        $this->installerLanguages = $mappedLanguages;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => ['setInstallerLocale', 15],
        ];
    }

    public function setInstallerLocale(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $locale = $this->detectLanguage($request);
        $request->attributes->set('_locale', $locale);
        $request->setLocale($locale);
    }

    private function detectLanguage(Request $request): string
    {
        $session = $request->getSession();

        // language is changed
        if ($request->query->has('language') && \in_array((string) $request->query->get('language'), $this->installerLanguages, true)) {
            $session->set('language', (string) $request->query->get('language'));

            return (string) $request->query->get('language');
        }

        // language was already set
        if ($session->has('language') && \in_array((string) $session->get('language'), $this->installerLanguages, true)) {
            return (string) $session->get('language');
        }

        // get initial language from the browser header
        if ($request->headers->has('Accept-Language')) {
            $browserLanguage = $request->getPreferredLanguage(array_keys($this->installerLanguages));
            $detectedLanguage = mb_strtolower(str_replace('_', '-', $browserLanguage ?? ''));

            if (\array_key_exists($detectedLanguage, $this->installerLanguages)) {
                $supportedLanguage = $this->installerLanguages[$detectedLanguage];
                $session->set('language', $supportedLanguage);

                return $supportedLanguage;
            }
        }

        $session->set('language', self::FALLBACK_LOCALE);

        return self::FALLBACK_LOCALE;
    }
}
