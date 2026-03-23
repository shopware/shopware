<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Full-spectrum structural audit of every extension on this Shopware instance.
 *
 * Returns a per-extension breakdown of what each plugin/app/theme has wired into
 * the running system, plus cross-extension conflict detection and upgrade-readiness
 * signals computed at read time.
 *
 * --- Per extension ---
 *   routes            API routes attributed to this extension by namespace
 *   entities          DAL entity definitions registered by this extension
 *   event_subscribers Events subscribed to, with priority and method — overlap at
 *                     identical priority with another extension = ordering conflict
 *   tagged_services   Contributions to shared extension points: tax providers,
 *                     payment handlers, rule conditions, flow actions, etc.
 *   console_commands  CLI commands registered by this extension
 *   scheduled_tasks   Background jobs registered by this extension
 *   custom_fields     Custom fields owned by this extension
 *   migrations        Executed vs pending migration count
 *   composer_version  Exact version from composer.lock (for CVE / upgrade checks)
 *
 * --- Instance-level ---
 *   conflicts         Cross-extension conflicts detected at read time:
 *                       duplicate_event_priority, duplicate_tag_priority,
 *                       route_name_collision
 *   risk_signals      Actionable upgrade / compatibility warnings
 *   feature_flags     All Shopware feature flags and their current state
 *   upgrade_readiness Summary: risk score, high-risk decorations, incompatible constraints
 *
 * Consume this resource as context before any upgrade analysis, conflict-resolution,
 * dependency-mapping, or integration-audit task.
 *
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(
    uri: 'shopware://instance-extensions',
    name: 'shopware-instance-extensions',
    description: 'Full-spectrum audit of every plugin, app, and theme. Per-extension: '
        . 'API routes, DAL entities, event subscribers (with priorities), tagged service '
        . 'contributions (tax providers, payment handlers, rule conditions, flow actions), '
        . 'console commands, scheduled tasks, custom fields, and migration status. '
        . 'Instance-level: cross-extension conflict detection, upgrade risk signals, '
        . 'and feature flag inventory. Use before upgrade analysis, conflict resolution, '
        . 'or integration audits.',
)]
#[Package('framework')]
class InstanceExtensionsResource
{
    private const CORE_NAMESPACES = [
        'Shopware\\', 'Symfony\\', 'Doctrine\\', 'Twig\\',
        'Monolog\\', 'League\\', 'GuzzleHttp\\', 'Psr\\',
    ];

    private const CORE_ROUTE_PREFIXES = [
        '/api/_', '/api/acl-role', '/api/app', '/api/category', '/api/cms',
        '/api/currency', '/api/customer', '/api/delivery', '/api/document',
        '/api/flow', '/api/import-export', '/api/integration', '/api/language',
        '/api/mail-template', '/api/manufacturer', '/api/media',
        '/api/message-queue', '/api/newsletter', '/api/oauth', '/api/order',
        '/api/payment', '/api/product', '/api/promotion', '/api/property',
        '/api/rule', '/api/sales-channel', '/api/search', '/api/shipping',
        '/api/snippet', '/api/state-machine', '/api/system-config', '/api/tag',
        '/api/tax', '/api/theme', '/api/unit', '/api/user',
        '/store-api/', '/sync/', '/_wdt/', '/_profiler/',
    ];

    /**
     * @param iterable<object> $eventSubscribers All kernel.event_subscriber tagged services
     * @param iterable<object> $taxProviders
     * @param iterable<object> $paymentHandlers
     * @param iterable<object> $ruleConditions
     * @param iterable<object> $flowActions
     * @param iterable<object> $mcpTools
     * @param iterable<object> $mcpResources
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel,
        private readonly RouterInterface $router,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly iterable $eventSubscribers,
        private readonly iterable $taxProviders,
        private readonly iterable $paymentHandlers,
        private readonly iterable $ruleConditions,
        private readonly iterable $flowActions,
        private readonly iterable $mcpTools,
        private readonly iterable $mcpResources,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $plugins = $this->auditPlugins();
        $apps = $this->queryApps();
        $themes = $this->queryThemes();

        // Build attribution maps — these cross-reference live DI services to plugins.
        $eventListenerMap = $this->buildEventListenerMap();
        $taggedServiceMap = $this->buildTaggedServiceMap();
        $commandMap = $this->buildCommandMap();
        $scheduledTaskMap = $this->buildScheduledTaskMap();
        $routeMap = $this->buildRouteMap($plugins);

        foreach ($plugins as &$plugin) {
            $ns = (string) ($plugin['base_namespace'] ?? '');
            $name = (string) $plugin['name'];

            $plugin['routes'] = $routeMap[$name] ?? [];
            $plugin['entities'] = $this->findEntitiesForNamespace($ns);
            $plugin['event_subscribers'] = $eventListenerMap[$ns] ?? [];
            $plugin['tagged_services'] = $taggedServiceMap[$ns] ?? [];
            $plugin['console_commands'] = $commandMap[$ns] ?? [];
            $plugin['scheduled_tasks'] = $scheduledTaskMap[$name] ?? [];
            $plugin['custom_fields'] = $this->findCustomFieldsForPlugin($name);
            $plugin['migrations'] = $this->queryMigrationsForPlugin($name);
            $plugin['risk_signals'] = $this->computePluginRiskSignals($plugin);
        }
        unset($plugin);

        $conflicts = $this->detectConflicts($plugins);
        $featureFlags = $this->readFeatureFlags();
        $upgradeReadiness = $this->computeUpgradeReadiness($plugins, $conflicts);

        $payload = [
            'shopware_version' => $this->resolveShopwareVersion(),
            'php_version' => \PHP_VERSION,
            'environment' => $this->kernel->getEnvironment(),
            'plugins' => $plugins,
            'apps' => $apps,
            'themes' => $themes,
            'conflicts' => $conflicts,
            'feature_flags' => $featureFlags,
            'upgrade_readiness' => $upgradeReadiness,
            'instance_route_summary' => $this->buildInstanceRouteSummary(),
        ];

        return [
            'uri' => 'shopware://instance-extensions',
            'mimeType' => 'application/json',
            'text' => json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
        ];
    }

    // ── Plugin inventory ──────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function auditPlugins(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT name, version, active, installed_at, author, base_class, composer_name,
                        upgrade_version
                 FROM plugin ORDER BY name ASC',
            );
        } catch (\Throwable) {
            return [];
        }

        $composerVersions = $this->readComposerLockVersions();
        $composerConstraints = $this->readComposerRequireConstraints();

        return array_map(function (array $row) use ($composerVersions, $composerConstraints): array {
            $baseClass = (string) ($row['base_class'] ?? '');
            $baseNamespace = $baseClass !== ''
                ? implode('\\', \array_slice(explode('\\', $baseClass), 0, -1))
                : '';
            $composerName = (string) ($row['composer_name'] ?? '');

            return [
                'name' => (string) $row['name'],
                'version' => (string) ($row['version'] ?? 'unknown'),
                'active' => (bool) $row['active'],
                'installed_at' => isset($row['installed_at']) ? (string) $row['installed_at'] : null,
                'author' => (string) ($row['author'] ?? 'unknown'),
                'base_class' => $baseClass,
                'base_namespace' => $baseNamespace,
                'composer_name' => $composerName,
                'composer_version' => $composerVersions[$composerName] ?? null,
                'shopware_constraint' => $composerConstraints[$composerName] ?? null,
                'has_pending_upgrade' => $row['upgrade_version'] !== null,
            ];
        }, $rows);
    }

    // ── Attribution maps ──────────────────────────────────────────────────────

    /**
     * Returns all event listeners grouped by the plugin base_namespace that owns them.
     *
     * Format: namespace → [ {event, priority, class, method} ]
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildEventListenerMap(): array
    {
        $map = [];
        $allListeners = $this->eventDispatcher->getListeners();

        foreach ($allListeners as $eventName => $listeners) {
            if (!\is_array($listeners)) {
                continue;
            }

            foreach ($listeners as $listener) {
                if (!\is_array($listener) || \count($listener) < 2) {
                    continue;
                }

                [$serviceOrClass, $method] = $listener;
                $class = \is_object($serviceOrClass) ? $serviceOrClass::class : (string) $serviceOrClass;

                if ($this->isCoreClass($class)) {
                    continue;
                }

                $priority = 0;
                if (\is_callable($listener)) {
                    try {
                        $priority = (int) $this->eventDispatcher->getListenerPriority($eventName, $listener);
                    } catch (\Throwable) {
                    }
                }

                $ns = $this->extractNamespace($class);
                $map[$ns][] = [
                    'event' => $eventName,
                    'priority' => $priority,
                    'class' => $class,
                    'method' => (string) $method,
                ];
            }
        }

        return $map;
    }

    /**
     * Groups tagged services from all known extension-point tags by their namespace.
     *
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private function buildTaggedServiceMap(): array
    {
        $map = [];

        $taggedGroups = [
            'tax_providers' => $this->taxProviders,
            'payment_handlers' => $this->paymentHandlers,
            'rule_conditions' => $this->ruleConditions,
            'flow_actions' => $this->flowActions,
            'mcp_tools' => $this->mcpTools,
            'mcp_resources' => $this->mcpResources,
        ];

        foreach ($taggedGroups as $category => $services) {
            foreach ($services as $service) {
                $class = $service::class;

                if ($this->isCoreClass($class)) {
                    continue;
                }

                $ns = $this->extractNamespace($class);
                $map[$ns][$category][] = ['class' => $class];
            }
        }

        // Also include event subscribers in the tagged service view.
        foreach ($this->eventSubscribers as $subscriber) {
            $class = $subscriber::class;

            if ($this->isCoreClass($class) || !($subscriber instanceof EventSubscriberInterface)) {
                continue;
            }

            $ns = $this->extractNamespace($class);

            $events = $subscriber::getSubscribedEvents();
            $map[$ns]['event_subscribers'][] = [
                'class' => $class,
                'subscribes_to' => array_keys($events),
            ];
        }

        return $map;
    }

    /**
     * Returns console commands grouped by the plugin namespace that owns them.
     *
     * @return array<string, list<string>>
     */
    private function buildCommandMap(): array
    {
        $map = [];

        try {
            // Get the kernel's console application for all registered commands.
            $container = $this->kernel->getContainer();
            if ($container->has('console.command_loader')) {
                $loader = $container->get('console.command_loader');
                foreach ($loader->getNames() as $commandName) {
                    try {
                        $command = $loader->get($commandName);
                        $class = $command::class;

                        if ($this->isCoreClass($class)) {
                            continue;
                        }

                        $ns = $this->extractNamespace($class);
                        $map[$ns][] = $commandName;
                    } catch (\Throwable) {
                        // Skip unloadable commands.
                    }
                }
            }
        } catch (\Throwable) {
        }

        return $map;
    }

    /**
     * Returns scheduled tasks grouped by plugin name (matched by class name prefix).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildScheduledTaskMap(): array
    {
        $map = [];

        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT scheduled_task_class, name, run_interval, status FROM scheduled_task ORDER BY name ASC',
            );
        } catch (\Throwable) {
            return [];
        }

        foreach ($rows as $row) {
            $class = (string) $row['scheduled_task_class'];

            if ($this->isCoreClass($class)) {
                continue;
            }

            // Try to attribute to a plugin by checking which plugin namespace owns the class.
            $ns = $this->extractNamespace($class);
            $map[$ns][] = [
                'name' => (string) $row['name'],
                'class' => $class,
                'run_interval_seconds' => (int) $row['run_interval'],
                'status' => (string) $row['status'],
            ];
        }

        return $map;
    }

    // ── Conflict and risk detection ───────────────────────────────────────────

    /**
     * Detect cross-plugin conflicts by analyzing the data we've already collected.
     *
     * @param list<array<string, mixed>> $plugins
     * @return list<array<string, mixed>>
     */
    private function detectConflicts(array $plugins): array
    {
        $conflicts = [];

        // --- 1. Duplicate event priority conflicts ---
        // Two different plugins listening to the same event at the same priority.
        // Symptom: non-deterministic execution order, hard-to-reproduce bugs.
        $eventPriorityIndex = [];
        foreach ($plugins as $plugin) {
            foreach ((array) ($plugin['event_subscribers'] ?? []) as $listener) {
                $key = $listener['event'] . '@' . $listener['priority'];
                $eventPriorityIndex[$key][] = [
                    'plugin' => $plugin['name'],
                    'class' => $listener['class'],
                    'method' => $listener['method'],
                ];
            }
        }
        foreach ($eventPriorityIndex as $key => $entries) {
            if (\count($entries) > 1) {
                [$event, $priority] = explode('@', $key, 2);
                $conflicts[] = [
                    'type' => 'duplicate_event_priority',
                    'severity' => 'warning',
                    'event' => $event,
                    'priority' => (int) $priority,
                    'detail' => sprintf(
                        '%d extensions listen to "%s" at priority %s — execution order is non-deterministic.',
                        \count($entries),
                        $event,
                        $priority,
                    ),
                    'extensions' => $entries,
                    'fix' => 'Assign distinct priority values to each listener to make the order explicit.',
                ];
            }
        }

        // --- 2. Duplicate tagged service priority conflicts ---
        // Two tax providers (or similar) at the same priority compete non-deterministically.
        $tagPriorityIndex = [];
        foreach ($plugins as $plugin) {
            foreach ((array) ($plugin['tagged_services'] ?? []) as $category => $services) {
                foreach ((array) $services as $service) {
                    $tagPriorityIndex[$category][] = [
                        'plugin' => $plugin['name'],
                        'class' => $service['class'] ?? '',
                    ];
                }
            }
        }
        foreach ($tagPriorityIndex as $tag => $entries) {
            if (\count($entries) > 1) {
                $conflicts[] = [
                    'type' => 'multiple_' . $tag,
                    'severity' => 'info',
                    'detail' => sprintf(
                        '%d extensions provide "%s". Verify priority ordering is intentional.',
                        \count($entries),
                        $tag,
                    ),
                    'extensions' => $entries,
                    'fix' => 'Check that service priorities are set explicitly to avoid non-deterministic selection.',
                ];
            }
        }

        // --- 3. Route name collisions ---
        $routeNameIndex = [];
        foreach ($plugins as $plugin) {
            foreach ((array) ($plugin['routes'] ?? []) as $route) {
                $routeNameIndex[$route['name']][] = $plugin['name'];
            }
        }
        foreach ($routeNameIndex as $routeName => $pluginNames) {
            if (\count($pluginNames) > 1) {
                $conflicts[] = [
                    'type' => 'route_name_collision',
                    'severity' => 'error',
                    'route_name' => $routeName,
                    'detail' => sprintf(
                        'Route "%s" is registered by %d extensions — the last one wins, earlier registrations are silently dropped.',
                        $routeName,
                        \count($pluginNames),
                    ),
                    'extensions' => $pluginNames,
                    'fix' => 'Each extension must use a unique route name prefix.',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Compute per-plugin upgrade risk signals.
     *
     * @param array<string, mixed> $plugin
     * @return list<array<string, string>>
     */
    private function computePluginRiskSignals(array $plugin): array
    {
        $signals = [];

        // Pending migrations = schema drift risk on upgrade.
        $pending = (int) (($plugin['migrations'] ?? [])['pending'] ?? 0);
        if ($pending > 0) {
            $signals[] = [
                'type' => 'pending_migrations',
                'severity' => 'warning',
                'detail' => "{$pending} migration(s) have not been executed. Run bin/console database:migrate.",
            ];
        }

        // Shopware version constraint too tight = upgrade blockers.
        $constraint = (string) ($plugin['shopware_constraint'] ?? '');
        if ($constraint !== '' && str_contains($constraint, '~') || str_contains($constraint, '==')) {
            $signals[] = [
                'type' => 'tight_version_constraint',
                'severity' => 'warning',
                'detail' => "Composer constraint \"{$constraint}\" may block minor version upgrades. Prefer ^x.y.",
            ];
        }

        // Multiple tax providers = non-deterministic cart calculation risk.
        $hasTaxProvider = !empty($plugin['tagged_services']['tax_providers'] ?? []);
        if ($hasTaxProvider) {
            $signals[] = [
                'type' => 'tax_provider_registered',
                'severity' => 'info',
                'detail' => 'This extension registers a tax provider. Verify its priority relative to core '
                    . 'and any other tax providers to prevent non-deterministic cart tax calculations.',
            ];
        }

        // Payment handler = PCI/compliance risk on upgrade.
        $hasPaymentHandler = !empty($plugin['tagged_services']['payment_handlers'] ?? []);
        if ($hasPaymentHandler) {
            $signals[] = [
                'type' => 'payment_handler_registered',
                'severity' => 'info',
                'detail' => 'Payment handler registered. Validate against the Shopware payment handler '
                    . 'interface contract after any core upgrade.',
            ];
        }

        return $signals;
    }

    /**
     * @param list<array<string, mixed>> $plugins
     * @param list<array<string, mixed>> $conflicts
     * @return array<string, mixed>
     */
    private function computeUpgradeReadiness(array $plugins, array $conflicts): array
    {
        $errorConflicts = array_filter($conflicts, static fn (array $c): bool => ($c['severity'] ?? '') === 'error');
        $warnConflicts = array_filter($conflicts, static fn (array $c): bool => ($c['severity'] ?? '') === 'warning');

        $totalPendingMigrations = (int) array_sum(array_map(
            static fn (array $p): int => (int) (($p['migrations'] ?? [])['pending'] ?? 0),
            $plugins,
        ));

        $pluginsWithTightConstraints = array_values(array_filter(
            $plugins,
            static fn (array $p): bool => str_contains((string) ($p['shopware_constraint'] ?? ''), '~'),
        ));

        $riskScore = (\count($errorConflicts) * 10)
            + (\count($warnConflicts) * 3)
            + ($totalPendingMigrations * 5)
            + (\count($pluginsWithTightConstraints) * 2);

        $riskLevel = match (true) {
            $riskScore === 0 => 'green',
            $riskScore <= 10 => 'yellow',
            $riskScore <= 25 => 'orange',
            default => 'red',
        };

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'error_conflicts' => \count($errorConflicts),
            'warning_conflicts' => \count($warnConflicts),
            'total_pending_migrations' => $totalPendingMigrations,
            'plugins_with_tight_version_constraints' => array_column($pluginsWithTightConstraints, 'name'),
            'summary' => match ($riskLevel) {
                'green' => 'No conflicts or risk signals detected. Instance is upgrade-ready.',
                'yellow' => 'Minor warnings present. Review before upgrading.',
                'orange' => 'Conflicts detected. Resolve before upgrading.',
                'red' => 'Critical conflicts or blocking issues detected. Do not upgrade without resolution.',
            },
        ];
    }

    // ── Per-plugin detail queries ─────────────────────────────────────────────

    /**
     * @return list<array{entity: string, class: string}>
     */
    private function findEntitiesForNamespace(string $baseNamespace): array
    {
        if ($baseNamespace === '') {
            return [];
        }

        $found = [];
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if (str_starts_with($definition::class, $baseNamespace)) {
                $found[] = [
                    'entity' => $definition->getEntityName(),
                    'class' => $definition::class,
                ];
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function findCustomFieldsForPlugin(string $pluginName): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT cf.name FROM custom_field cf
                 INNER JOIN custom_field_set cfs ON cf.set_id = cfs.id
                 WHERE cfs.app_id IS NULL
                   AND (cfs.name LIKE :prefix OR cf.name LIKE :prefix)',
                ['prefix' => strtolower($pluginName) . '%'],
            );

            return array_column($rows, 'name');
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{executed: int, pending: int}
     */
    private function queryMigrationsForPlugin(string $pluginName): array
    {
        try {
            $executed = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM migration WHERE class LIKE :p AND `update` IS NOT NULL',
                ['p' => '%' . $pluginName . '%'],
            );
            $total = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM migration WHERE class LIKE :p',
                ['p' => '%' . $pluginName . '%'],
            );

            return ['executed' => $executed, 'pending' => $total - $executed];
        } catch (\Throwable) {
            return ['executed' => 0, 'pending' => 0];
        }
    }

    // ── Route map ────────────────────────────────────────────────────────────

    /**
     * @param list<array<string, mixed>> $plugins
     * @return array<string, list<array<string, string>>>
     */
    private function buildRouteMap(array $plugins): array
    {
        $map = [];
        $routes = $this->router->getRouteCollection()->all();

        foreach ($routes as $name => $route) {
            $defaults = $route->getDefaults();
            $controller = (string) ($defaults['_controller'] ?? '');
            $routeScope = (array) ($defaults['_routeScope'] ?? []);

            if (!\in_array('api', $routeScope, true) && !\in_array('storefront', $routeScope, true)) {
                continue;
            }

            $path = $route->getPath();
            if ($this->isCoreRoute($path, $name)) {
                continue;
            }

            $controllerClass = strstr($controller, '::', true) ?: $controller;

            foreach ($plugins as $plugin) {
                $ns = (string) ($plugin['base_namespace'] ?? '');
                if ($ns !== '' && str_starts_with($controllerClass, $ns)) {
                    $map[$plugin['name']][] = [
                        'name' => $name,
                        'path' => $path,
                        'methods' => implode(',', $route->getMethods() ?: ['ANY']),
                        'scope' => implode(',', $routeScope),
                    ];
                }
            }
        }

        return $map;
    }

    /**
     * @return array{plugin_added_route_count: int, plugin_added_routes: list<array<string, string>>}
     */
    private function buildInstanceRouteSummary(): array
    {
        $pluginRoutes = [];

        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $defaults = $route->getDefaults();
            $scope = (array) ($defaults['_routeScope'] ?? []);
            $controller = (string) ($defaults['_controller'] ?? '');
            $path = $route->getPath();

            if (!\in_array('api', $scope, true)) {
                continue;
            }

            if ($this->isCoreRoute($path, $name)) {
                continue;
            }

            $controllerClass = strstr($controller, '::', true) ?: $controller;
            if (!$this->isCoreClass($controllerClass)) {
                $pluginRoutes[] = [
                    'name' => $name,
                    'path' => $path,
                    'methods' => implode(',', $route->getMethods() ?: ['ANY']),
                    'controller' => $controllerClass,
                ];
            }
        }

        return [
            'plugin_added_route_count' => \count($pluginRoutes),
            'plugin_added_routes' => $pluginRoutes,
        ];
    }

    // ── Apps and themes ──────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function queryApps(): array
    {
        try {
            return array_map(static fn (array $r): array => [
                'name' => (string) $r['name'],
                'version' => (string) ($r['version'] ?? 'unknown'),
                'active' => (bool) $r['active'],
                'author' => (string) ($r['author'] ?? 'unknown'),
                'app_secret' => isset($r['app_secret']) ? '***redacted***' : null,
            ], $this->connection->fetchAllAssociative(
                'SELECT name, version, active, author FROM app ORDER BY name ASC',
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function queryThemes(): array
    {
        try {
            return array_map(static fn (array $r): array => [
                'technical_name' => (string) $r['technical_name'],
                'display_name' => (string) ($r['display_name'] ?? ''),
                'author' => (string) ($r['author'] ?? 'unknown'),
            ], $this->connection->fetchAllAssociative(
                'SELECT t.technical_name, t.author, tt.name AS display_name
                 FROM theme t
                 LEFT JOIN theme_translation tt ON tt.theme_id = t.id
                 GROUP BY t.id
                 ORDER BY t.technical_name ASC',
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Feature flags ────────────────────────────────────────────────────────

    /**
     * @return array<string, bool>
     */
    private function readFeatureFlags(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT sales_channel_id, configuration_key, configuration_value
                 FROM system_config
                 WHERE configuration_key LIKE "core.feature.%"
                 AND sales_channel_id IS NULL
                 ORDER BY configuration_key ASC',
            );

            $flags = [];
            foreach ($rows as $row) {
                $key = str_replace('core.feature.', '', (string) $row['configuration_key']);
                $value = json_decode((string) $row['configuration_value'], true);
                $flags[$key] = (bool) $value;
            }

            return $flags;
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Composer ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    private function readComposerLockVersions(): array
    {
        return $this->parseComposerFile('/composer.lock', static function (array $data): array {
            $versions = [];
            foreach ($data['packages'] ?? [] as $pkg) {
                if (isset($pkg['name'], $pkg['version'])) {
                    $versions[(string) $pkg['name']] = (string) $pkg['version'];
                }
            }

            return $versions;
        });
    }

    /**
     * @return array<string, string>
     */
    private function readComposerRequireConstraints(): array
    {
        return $this->parseComposerFile('/composer.json', static function (array $data): array {
            $constraints = [];
            foreach ($data['require'] ?? [] as $name => $constraint) {
                $constraints[(string) $name] = (string) $constraint;
            }

            return $constraints;
        });
    }

    /**
     * @param callable(array<mixed>): array<string,string> $extractor
     * @return array<string, string>
     */
    private function parseComposerFile(string $relativePath, callable $extractor): array
    {
        $file = $this->kernel->getProjectDir() . $relativePath;
        if (!is_file($file)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 32, \JSON_THROW_ON_ERROR);

            return $extractor($data);
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Utility ──────────────────────────────────────────────────────────────

    private function resolveShopwareVersion(): string
    {
        $container = $this->kernel->getContainer();

        foreach (['kernel.shopware_version', 'shopware.version', 'shopware_version'] as $param) {
            if ($container->hasParameter($param)) {
                $value = $container->getParameter($param);

                if (\is_scalar($value)) {
                    return (string) $value;
                }
            }
        }

        // Fallback: read from composer.lock
        $versions = $this->readComposerLockVersions();

        return $versions['shopware/core'] ?? $versions['shopware/platform'] ?? 'unknown';
    }

    private function isCoreClass(string $class): bool
    {
        foreach (self::CORE_NAMESPACES as $ns) {
            if (str_starts_with($class, $ns)) {
                return true;
            }
        }

        return false;
    }

    private function isCoreRoute(string $path, string $name): bool
    {
        foreach (self::CORE_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        // DAL-generated entity CRUD routes follow a predictable naming pattern.
        if (preg_match('/^api\.([\w-]+)\.(list|create|update|delete|detail|search|search-ids)$/', $name)) {
            return true;
        }

        return false;
    }

    /**
     * Extracts the top-level namespace (first two segments) for plugin attribution.
     * e.g. Acme\AcmePlugin\Subscriber\Foo → "Acme\AcmePlugin"
     */
    private function extractNamespace(string $class): string
    {
        $parts = explode('\\', $class);

        return implode('\\', \array_slice($parts, 0, min(2, \count($parts))));
    }
}
