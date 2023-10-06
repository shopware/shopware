<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('storefront')]
class UpdateSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly ThemeLifecycleService $themeLifecycleService,
        private readonly EntityRepository $salesChannelRepository
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
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

        if ($context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING)) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->getAssociation('themes')
            ->addFilter(new EqualsFilter('active', true));

        $alreadyCompiled = [];
        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->salesChannelRepository->search($criteria, $context) as $salesChannel) {
            $themes = $salesChannel->getExtension('themes');
            if (!$themes instanceof ThemeCollection) {
                continue;
            }

            $failedThemes = [];

            /** @var ThemeEntity $theme */
            foreach ($themes as $theme) {
                // NEXT-21735 - his is covered randomly
                // @codeCoverageIgnoreStart
                if (\in_array($theme->getId(), $alreadyCompiled, true) !== false) {
                    continue;
                }
                // @codeCoverageIgnoreEnd

                try {
                    $alreadyCompiled += $this->themeService->compileThemeById($theme->getId(), $context);
                } catch (ThemeCompileException $e) {
                    $failedThemes[] = $theme->getName();
                    $alreadyCompiled[] = $theme->getId();
                }
            }

            if (!empty($failedThemes)) {
                $event->appendPostUpdateMessage('Theme(s): ' . implode(', ', $failedThemes) . ' could not be recompiled.');
            }
        }
    }
}
