<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Message;

use Shopware\Administration\Notification\NotificationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeCompilerInterface;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('storefront')]
final class CompileThemeHandler
{
    public function __construct(
        private readonly ThemeCompilerInterface $themeCompiler,
        private readonly AbstractConfigLoader $configLoader,
        private readonly StorefrontPluginRegistryInterface $extensionRegistry,
        private readonly NotificationService $notificationService,
        private readonly EntityRepository $saleschannelRepository
    ) {
    }

    public function __invoke(CompileThemeMessage $message): void
    {
        $message->getContext()->addState(ThemeService::STATE_NO_QUEUE);
        $this->themeCompiler->compileTheme(
            $message->getSalesChannelId(),
            $message->getThemeId(),
            $this->configLoader->load($message->getThemeId(), $message->getContext()),
            $this->extensionRegistry->getConfigurations(),
            $message->isWithAssets(),
            $message->getContext()
        );

        if ($message->getContext()->getScope() !== Context::USER_SCOPE) {
            return;
        }
        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->saleschannelRepository->search(
            new Criteria([$message->getSalesChannelId()]),
            $message->getContext()
        )->first();

        if ($salesChannel === null) {
            throw ThemeException::salesChannelNotFound($message->getSalesChannelId());
        }

        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'info',
                'message' => 'Compilation for sales channel ' . $salesChannel->getName() . ' completed',
                'requiredPrivileges' => [],
            ],
            $message->getContext()
        );
    }
}
