<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UpdateSubscriber implements EventSubscriberInterface
{
    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var ThemeLifecycleService
     */
    private $themeLifecycleService;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    public function __construct(
        ThemeService $themeService,
        ThemeLifecycleService $themeLifecycleService,
        EntityRepositoryInterface $themeRepository
    ) {
        $this->themeService = $themeService;
        $this->themeLifecycleService = $themeLifecycleService;
        $this->themeRepository = $themeRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            UpdatePostFinishEvent::class => 'updateFinished',
        ];
    }

    /**
     * @internal
     */
    public function updateFinished(UpdatePostFinishEvent $event): void
    {
        $context = $event->getContext();
        $this->themeLifecycleService->refreshThemes($context);

        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('technicalName', 'Storefront'));
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->first();
        if (!$theme) {
            throw new \RuntimeException('Default theme not found');
        }

        foreach ($theme->getSalesChannels() as $salesChannel) {
            $salesChannelId = $salesChannel->getId();
            $this->themeService->compileTheme($salesChannelId, $theme->getId(), $context);
        }
    }
}
