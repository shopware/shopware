<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Store\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Event\AppExtensionLoadedEvent;
use Shopware\Core\Framework\Store\Event\PluginExtensionLoadedEvent;
use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\ThemeCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Marks plugin- and app-backed extensions as themes when Storefront recognizes them.
 *
 * For plugins, an extension is a theme when its base class implements {@see ThemeInterface}.
 * For apps, an extension is a theme when its technical name appears in the storefront `theme` table.
 *
 * @internal
 */
#[Package('framework')]
class ExtensionThemeDetectionSubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string>|null
     */
    private ?array $installedThemeNames = null;

    /**
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(private readonly EntityRepository $themeRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginExtensionLoadedEvent::class => 'detectPluginTheme',
            AppExtensionLoadedEvent::class => 'detectAppTheme',
        ];
    }

    public function detectPluginTheme(PluginExtensionLoadedEvent $event): void
    {
        $baseClass = $event->plugin->getBaseClass();
        if (!class_exists($baseClass)) {
            return;
        }

        $implementedInterfaces = class_implements($baseClass) ?: [];

        if (\array_key_exists(ThemeInterface::class, $implementedInterfaces)) {
            $event->isTheme = true;
        }
    }

    public function detectAppTheme(AppExtensionLoadedEvent $event): void
    {
        $event->isTheme = \in_array($event->app->getName(), $this->getInstalledThemeNames($event->context), true);
    }

    public function reset(): void
    {
        $this->installedThemeNames = null;
    }

    /**
     * @return array<string>
     */
    private function getInstalledThemeNames(Context $context): array
    {
        if ($this->installedThemeNames !== null) {
            return $this->installedThemeNames;
        }

        $themeNameAggregationName = 'theme_names';
        $criteria = new Criteria();
        $criteria->addAggregation(new TermsAggregation($themeNameAggregationName, 'technicalName'));

        $themeNameAggregation = $this->themeRepository->aggregate($criteria, $context)->get($themeNameAggregationName);
        if (!$themeNameAggregation instanceof TermsResult) {
            return $this->installedThemeNames = [];
        }

        return $this->installedThemeNames = $themeNameAggregation->getKeys();
    }
}
