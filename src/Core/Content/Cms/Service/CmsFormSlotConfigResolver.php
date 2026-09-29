<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('discovery')]
readonly class CmsFormSlotConfigResolver
{
    /**
     * @internal
     *
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param EntityRepository<LandingPageCollection> $landingPageRepository
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<CmsSlotCollection> $cmsSlotRepository
     */
    public function __construct(
        private EntityRepository $categoryRepository,
        private EntityRepository $landingPageRepository,
        private EntityRepository $productRepository,
        private EntityRepository $cmsSlotRepository,
        private SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @return array{receivers: array<string, string>, message: string}
     */
    public function resolve(SalesChannelContext $context, ?string $slotId, ?string $navigationId, ?string $entityName): array
    {
        $slotConfig = $this->getSlotConfig($context, $slotId, $navigationId, $entityName);

        if (!\is_array($slotConfig['receivers']) || $slotConfig['receivers'] === []) {
            $slotConfig['receivers'] = [
                $this->systemConfigService->getString('core.basicInformation.email', $context->getSalesChannelId()) => $this->systemConfigService->getString('core.basicInformation.shopName', $context->getSalesChannelId()),
            ];
        } else {
            $slotConfig['receivers'] = \array_combine($slotConfig['receivers'], $slotConfig['receivers']);
        }

        if (!\is_string($slotConfig['message'])) {
            $slotConfig['message'] = '';
        }

        return $slotConfig;
    }

    /**
     * @return array{receivers: array<int, string>|null, message: string|null}
     */
    private function getSlotConfig(SalesChannelContext $context, ?string $slotId, ?string $navigationId, ?string $entityName): array
    {
        $slotConfig = ['receivers' => null, 'message' => null];

        if (!$slotId) {
            return $slotConfig;
        }

        if ($navigationId) {
            $slotConfig = $this->getSlotConfigFromEntity($context, $slotId, $navigationId, $entityName);

            if (\is_array($slotConfig['receivers']) && \is_string($slotConfig['message'])) {
                return $slotConfig;
            }
        }

        $criteria = new Criteria([$slotId]);
        $slot = $this->cmsSlotRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$slot) {
            return $slotConfig;
        }

        $config = $slot->getTranslated()['config'] ?? null;

        if (\is_array($config)) {
            if (!\is_array($slotConfig['receivers'])
                && \is_array($config['mailReceiver'] ?? null)
                && \is_array($config['mailReceiver']['value'] ?? null)
            ) {
                $slotConfig['receivers'] = $config['mailReceiver']['value'];
            }

            if (!\is_string($slotConfig['message'])
                && \is_array($config['confirmationText'] ?? null)
                && \is_string($config['confirmationText']['value'] ?? null)
            ) {
                $slotConfig['message'] = $config['confirmationText']['value'];
            }
        }

        return $slotConfig;
    }

    /**
     * @return array{receivers: array<int, string>|null, message: string|null}
     */
    private function getSlotConfigFromEntity(SalesChannelContext $context, string $slotId, string $navigationId, ?string $entityName = null): array
    {
        $slotConfig = ['receivers' => null, 'message' => null];

        $criteria = new Criteria([$navigationId]);

        $entity = match ($entityName) {
            ProductDefinition::ENTITY_NAME => $this->productRepository->search($criteria, $context->getContext())->getEntities()->first(),
            LandingPageDefinition::ENTITY_NAME => $this->landingPageRepository->search($criteria, $context->getContext())->getEntities()->first(),
            default => $this->categoryRepository->search($criteria, $context->getContext())->getEntities()->first(),
        };

        if (!$entity || !$entity->getSlotConfig() || !\array_key_exists($slotId, $entity->getSlotConfig())) {
            return $slotConfig;
        }

        $config = $entity->getSlotConfig()[$slotId];

        if (!\is_array($config)) {
            return $slotConfig;
        }

        if (\is_array($config['mailReceiver'] ?? null) && \is_array($config['mailReceiver']['value'] ?? null)) {
            $slotConfig['receivers'] = $config['mailReceiver']['value'];
        }

        if (\is_array($config['confirmationText'] ?? null) && \is_string($config['confirmationText']['value'] ?? null)) {
            $slotConfig['message'] = $config['confirmationText']['value'];
        }

        return $slotConfig;
    }
}
