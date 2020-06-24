<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Theme\ThemeCollection;
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
    private $salesChannelRepository;

    public function __construct(
        ThemeService $themeService,
        ThemeLifecycleService $themeLifecycleService,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->themeService = $themeService;
        $this->themeLifecycleService = $themeLifecycleService;
        $this->salesChannelRepository = $salesChannelRepository;
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
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->getAssociation('themes')
            ->addFilter(new EqualsFilter('active', true));

        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->salesChannelRepository->search($criteria, $context) as $salesChannel) {
            $themes = $salesChannel->getExtension('themes');
            if (!$themes instanceof ThemeCollection) {
                continue;
            }

            foreach ($themes as $theme) {
                $salesChannelId = $salesChannel->getId();
                $this->themeService->compileTheme($salesChannelId, $theme->getId(), $context);
            }
        }
    }
}
