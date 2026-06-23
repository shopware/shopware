<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Store\Subscriber;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Event\ExtensionLoadedEvent;
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
            ExtensionLoadedEvent::class => 'detectTheme',
        ];
    }

    public function detectTheme(ExtensionLoadedEvent $event): void
    {
        $source = $event->source;

        if ($source instanceof PluginEntity) {
            if ($this->isPluginTheme($source)) {
                $event->extension->setIsTheme(true);
            }

            return;
        }

        if ($this->isAppTheme($source, $event->context)) {
            $event->extension->setIsTheme(true);
        }
    }

    public function reset(): void
    {
        $this->installedThemeNames = null;
    }

    private function isPluginTheme(PluginEntity $plugin): bool
    {
        $baseClass = $plugin->getBaseClass();

        return class_exists($baseClass) && is_subclass_of($baseClass, ThemeInterface::class);
    }

    private function isAppTheme(AppEntity $app, Context $context): bool
    {
        return \in_array($app->getName(), $this->getInstalledThemeNames($context), true);
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
