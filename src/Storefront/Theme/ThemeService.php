<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigLoader;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeConfigException;
use Shopware\Storefront\Theme\Exception\ThemeConfigException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\Message\CompileThemeMessage;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\Validator\SCSSValidator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Package('framework')]
class ThemeService implements ResetInterface
{
    public const CONFIG_THEME_COMPILE_ASYNC = 'core.storefrontSettings.asyncThemeCompilation';
    public const STATE_NO_QUEUE = 'state-no-queue';

    private bool $notified = false;

    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     * @param EntityRepository<EntityCollection<Entity>> $themeSalesChannelRepository
     */
    public function __construct(
        private readonly StorefrontPluginRegistry $extensionRegistry,
        private readonly EntityRepository $themeRepository,
        private readonly EntityRepository $themeSalesChannelRepository,
        private readonly ThemeCompilerInterface $themeCompiler,
        private readonly AbstractScssCompiler $scssCompiler,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AbstractConfigLoader $configLoader,
        private readonly Connection $connection,
        private readonly SystemConfigService $configService,
        private readonly MessageBusInterface $messageBus,
        private readonly NotificationService $notificationService,
        private readonly ThemeMergedConfigBuilder $mergedConfigBuilder,
        private readonly ThemeRuntimeConfigService $themeRuntimeConfigService,
    ) {
    }

    /**
     * Only compiles a single theme/saleschannel combination.
     * Use `compileThemeById` to compile all dependend saleschannels
     */
    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        Context $context,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
        bool $withAssets = true
    ): void {
        if ($this->isAsyncCompilation($context)) {
            $this->handleAsync($salesChannelId, $themeId, $withAssets, $context);

            return;
        }

        $themeConfig = $this->configLoader->load($themeId, $context);
        $this->themeCompiler->compileTheme(
            $salesChannelId,
            $themeId,
            $themeConfig,
            $configurationCollection ?? $this->extensionRegistry->getConfigurations(),
            $withAssets,
            $context
        );

        // refresh the runtime config only if not using the StaticFileConfigLoader (no database)
        if (!$this->configLoader instanceof StaticFileConfigLoader) {
            $this->themeRuntimeConfigService->refreshRuntimeConfig(
                $themeId,
                $themeConfig,
                $context,
                true,
                $configurationCollection
            );
        }
    }

    /**
     * Compiles all dependend saleschannel/Theme combinations
     *
     * @return array<int, string>
     */
    public function compileThemeById(
        string $themeId,
        Context $context,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
        bool $withAssets = true
    ): array {
        $mappings = $this->getThemeDependencyMapping($themeId);
        $compiledThemeIds = [];
        foreach ($mappings as $mapping) {
            $this->compileTheme(
                $mapping->getSalesChannelId(),
                $mapping->getThemeId(),
                $context,
                $configurationCollection ?? $this->extensionRegistry->getConfigurations(),
                $withAssets
            );

            $compiledThemeIds[] = $mapping->getThemeId();
        }

        return $compiledThemeIds;
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function updateTheme(string $themeId, ?array $config, ?string $parentThemeId, Context $context): void
    {
        $criteria = (new Criteria([$themeId]))
            ->addAssociation('salesChannels');

        $theme = $this->themeRepository->search($criteria, $context)->getEntities()->first();
        if (!$theme) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $data = ['id' => $themeId];
        if ($config) {
            foreach ($config as $key => $value) {
                $data['configValues'][$key] = $value;
            }
        }

        if ($parentThemeId) {
            $data['parentThemeId'] = $parentThemeId;
        }

        if (\array_key_exists('configValues', $data)) {
            $this->dispatcher->dispatch(new ThemeConfigChangedEvent($themeId, $data['configValues']));
        }

        if (\array_key_exists('configValues', $data) && $theme->getConfigValues()) {
            $submittedChanges = $data['configValues'];
            $currentConfig = $theme->getConfigValues();
            $data['configValues'] = array_replace_recursive($currentConfig, $data['configValues']);

            foreach ($submittedChanges as $key => $changes) {
                if (isset($changes['value']) && \is_array($changes['value']) && isset($currentConfig[(string) $key]) && \is_array($currentConfig[(string) $key])) {
                    $data['configValues'][$key]['value'] = array_unique($changes['value']);
                }
            }
        }

        $this->themeRepository->update([$data], $context);

        if ($theme->getSalesChannels() === null) {
            // refresh runtime config here as theme will not be compiled later
            $this->themeRuntimeConfigService->refreshConfigValues($themeId, $context);

            return;
        }

        $this->compileThemeById($themeId, $context, null, false);
    }

    public function assignTheme(string $themeId, string $salesChannelId, Context $context, bool $skipCompile = false): bool
    {
        $this->connection->transactional(function () use ($themeId, $salesChannelId, $context, $skipCompile): void {
            if (!$skipCompile) {
                $this->compileTheme($salesChannelId, $themeId, $context);
            }

            $this->themeSalesChannelRepository->upsert([[
                'themeId' => $themeId,
                'salesChannelId' => $salesChannelId,
            ]], $context);
        });

        $this->dispatcher->dispatch(new ThemeAssignedEvent($themeId, $salesChannelId));

        return true;
    }

    public function resetTheme(string $themeId, Context $context): void
    {
        $theme = $this->themeRepository->search(new Criteria([$themeId]), $context)->getEntities()->first();
        if (!$theme) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $data = ['id' => $themeId];
        $data['configValues'] = null;

        $this->dispatcher->dispatch(new ThemeConfigResetEvent($themeId));

        $this->themeRepository->update([$data], $context);

        // Refresh runtime config after resetting theme config
        $this->themeRuntimeConfigService->refreshConfigValues($themeId, $context);
    }

    /**
     * Validates if the theme config can be compiled in SCSS.
     *
     * @param array<string, mixed> $config
     * @param array<int, string> $customAllowedRegex
     *
     * @return array<string, mixed>
     */
    public function validateThemeConfig(
        string $themeId,
        array $config,
        Context $context,
        array $customAllowedRegex = [],
        bool $sanitize = false
    ): array {
        // Get the merged theme config including inherited parent themes.
        $themeConfig = $this->getPlainThemeConfiguration($themeId, $context);

        // Single validation errors are collected in a wrapping exception.
        $themeConfigException = new ThemeConfigException();

        foreach ($config as $name => &$field) {
            // Lookup the field in the original theme config to get the field type.
            $fieldConfig = $themeConfig['fields'][$name] ?? null;

            // Skip fields that are not editable or excluded from SCSS compilation.
            if (!$fieldConfig
                || $fieldConfig['editable'] === false
                || $fieldConfig['scss'] === false) {
                continue;
            }

            $changedField = [
                'name' => $name,
                'value' => $field['value'],
                'type' => $fieldConfig['type'],
            ];

            try {
                $field['value'] = SCSSValidator::validate(
                    $this->scssCompiler,
                    $changedField,
                    $customAllowedRegex,
                    $sanitize
                );
            } catch (\Throwable $exception) {
                $themeConfigException->add($exception);
            }
        }

        // Check if there are any validation errors.
        $themeConfigException->tryToThrow();

        return $config;
    }

    /**
     * @throws InvalidThemeConfigException
     * @throws ThemeException
     * @throws InconsistentCriteriaIdsException
     *
     * @deprecated tag:v6.8.0 Use `getPlainThemeConfiguration` if you do not need translated labels or help texts or
     * getThemeConfigurationFieldStructure if you need structure with translations
     *
     * @return array<string, mixed>
     */
    public function getThemeConfiguration(string $themeId, bool $translate, Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getPlainThemeConfiguration')
        );

        return $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $context, $translate);
    }

    /**
     * @throws InvalidThemeConfigException
     * @throws ThemeException
     * @throws InconsistentCriteriaIdsException
     *
     * @return array<string, mixed>
     */
    public function getPlainThemeConfiguration(string $themeId, Context $context): array
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $translate = \func_num_args() === 3 ? func_get_arg(2) : false;

            return $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $context, $translate);
        }

        return $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $context);
    }

    /**
     * @deprecated tag:v6.8.0 Use `getThemeConfigurationFieldStructure` instead
     *
     * @return array<string, mixed>
     */
    public function getThemeConfigurationStructuredFields(string $themeId, bool $translate, Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getStructuredThemeConfiguration')
        );

        return $this->mergedConfigBuilder->getThemeConfigurationFieldStructure($themeId, $context, $translate);
    }

    /**
     * @return array<string, mixed>
     */
    public function getThemeConfigurationFieldStructure(string $themeId, Context $context): array
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $translate = \func_num_args() === 3 ? func_get_arg(2) : false;

            return $this->mergedConfigBuilder->getThemeConfigurationFieldStructure($themeId, $context, $translate);
        }

        return $this->mergedConfigBuilder->getThemeConfigurationFieldStructure($themeId, $context);
    }

    public function getThemeDependencyMapping(string $themeId): ThemeSalesChannelCollection
    {
        $mappings = new ThemeSalesChannelCollection();
        $themeData = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(theme.id)) as id, LOWER(HEX(childTheme.id)) as dependentId,
            LOWER(HEX(tsc.sales_channel_id)) as saleschannelId,
            LOWER(HEX(dtsc.sales_channel_id)) as dsaleschannelId
            FROM theme
            LEFT JOIN theme as childTheme ON childTheme.parent_theme_id = theme.id
            LEFT JOIN theme_sales_channel as tsc ON theme.id = tsc.theme_id
            LEFT JOIN theme_sales_channel as dtsc ON childTheme.id = dtsc.theme_id
            WHERE theme.id = :id',
            ['id' => Uuid::fromHexToBytes($themeId)]
        );

        foreach ($themeData as $data) {
            if (isset($data['id']) && isset($data['saleschannelId']) && $data['id'] === $themeId) {
                $mappings->add(new ThemeSalesChannel($data['id'], $data['saleschannelId']));
            }
            if (isset($data['dependentId']) && isset($data['dsaleschannelId'])) {
                $mappings->add(new ThemeSalesChannel($data['dependentId'], $data['dsaleschannelId']));
            }
        }

        return $mappings;
    }

    public function reset(): void
    {
        $this->notified = false;
    }

    private function handleAsync(
        string $salesChannelId,
        string $themeId,
        bool $withAssets,
        Context $context
    ): void {
        $this->messageBus->dispatch(
            new CompileThemeMessage(
                $salesChannelId,
                $themeId,
                $withAssets,
                $context
            )
        );

        if ($this->notified !== true && $context->getScope() === Context::USER_SCOPE) {
            $this->notificationService->createNotification(
                [
                    'id' => Uuid::randomHex(),
                    'status' => 'info',
                    'message' => 'The compilation of the changes will be started in the background. You may see the changes with delay (approx. 1 minute). You will receive a notification if the compilation is done.',
                    'requiredPrivileges' => [],
                ],
                $context
            );
            $this->notified = true;
        }
    }

    private function isAsyncCompilation(Context $context): bool
    {
        if ($this->configLoader instanceof StaticFileConfigLoader) {
            return false;
        }

        return $this->configService->get(self::CONFIG_THEME_COMPILE_ASYNC) && !$context->hasState(self::STATE_NO_QUEUE);
    }
}
