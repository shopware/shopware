<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @internal
 */
#[Package('core')]
class InstallerLocaleListener implements EventSubscriberInterface
{
    final public const FALLBACK_LOCALE = 'en';

    /**
     * @var list<string>
     */
    private readonly array $installerLanguages;

    /**
     * @param array<string, string> $installerLanguages
     */
    public function __construct(array $installerLanguages)
    {
        $this->installerLanguages = array_keys($installerLanguages);
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

        // get initial language from browser header
        if ($request->headers->has('HTTP_ACCEPT_LANGUAGE')) {
            $browserLanguage = explode(',', $request->headers->get('HTTP_ACCEPT_LANGUAGE', ''));
            $browserLanguage = mb_strtolower(mb_substr($browserLanguage[0], 0, 2));

            if (\in_array($browserLanguage, $this->installerLanguages, true)) {
                $session->set('language', $browserLanguage);

                return $browserLanguage;
            }
        }

        $session->set('language', self::FALLBACK_LOCALE);

        return self::FALLBACK_LOCALE;
    }
}
