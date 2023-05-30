<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('storefront')]
class FirstRunWizardSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly ThemeLifecycleService $themeLifecycleService,
        private readonly EntityRepository $themeRepository,
        private readonly EntityRepository $themeSalesChannelRepository,
        private readonly EntityRepository $salesChannelRepository
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FirstRunWizardFinishedEvent::class => 'frwFinished',
        ];
    }

    public function frwFinished(FirstRunWizardFinishedEvent $event): void
    {
        // only run on open -> completed|failed transition
        if (!$event->getPreviousState()->isOpen() || $event->getState()->isOpen()) {
            return;
        }

        $context = $event->getContext();

        $this->themeLifecycleService->refreshThemes($context);

        $themeCriteria = new Criteria();
        $themeCriteria->addAssociation('salesChannels');
        $themeCriteria->addFilter(new EqualsFilter('technicalName', 'Storefront'));
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($themeCriteria, $context)->first();
        if (!$theme) {
            throw new \RuntimeException('Default theme not found');
        }

        $themeSalesChannels = $theme->getSalesChannels();
        // only run if the themes are not already initialised
        if ($themeSalesChannels && $themeSalesChannels->count() > 0) {
            return;
        }

        $salesChannelCriteria = new Criteria();
        $salesChannelCriteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $salesChannelIds = $this->salesChannelRepository->search($salesChannelCriteria, $context)->getIds();
        foreach ($salesChannelIds as $id) {
            $this->themeService->compileTheme($id, $theme->getId(), $context);
            $this->themeSalesChannelRepository->upsert([[
                'themeId' => $theme->getId(),
                'salesChannelId' => $id,
            ]], $context);
        }
    }
}
