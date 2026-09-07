# Testing framework extraction scope

Status: scaffold and source inventory only; none of the classes below has been extracted yet.

The package must provide the Shopware test bootstrap and the complete shared test foundation. Its releases must be independent of core releases. Runtime integration still requires compatible Shopware, Symfony, Doctrine, and PHPUnit APIs.

## Bootstrap

Move the reusable responsibilities of `src/Core/TestBootstrapper.php` into `Shopware\TestSuite\TestBootstrapper`:

- Project discovery, explicit project directory, Composer class loader, and environment loading.
- Test database URL selection, installation, force-install options, and console output.
- Plugin discovery, calling-plugin detection, installation/activation, and plugin development autoloading.
- Optional commercial plugin activation.
- Kernel preparation and shutdown, with PHPUnit completion-guard registration before installation.

Preserve the existing fluent configuration surface during extraction. Project discovery must work from an installed Composer package; the current fallback starts at core’s `__DIR__` and cannot simply be copied unchanged.

`getStaticAnalyzeKernel()` directly depends on `Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel`. Keep this integration explicitly accounted for, but resolve its version-dependent implementation separately from normal PHPUnit bootstrap.

`src/Core/TestBootstrap.php` also includes platform-specific Danger autoloading and a PHPUnit-internal source-map pre-warm. The package entry point should bootstrap a consuming project without assuming the platform’s vendor-bin layout. Evaluate the pre-warm independently for each supported PHPUnit major.

## Complete base inventory

All files in `src/Core/Framework/Test/TestCaseBase/` are required extraction scope. Keep their existing short names under `Shopware\TestSuite\TestCaseBase\` initially:

| Existing file | Planned package file |
| --- | --- |
| `src/Core/Framework/Test/TestCaseBase/AdminApiTestBehaviour.php` | `src/TestCaseBase/AdminApiTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/AdminFunctionalTestBehaviour.php` | `src/TestCaseBase/AdminFunctionalTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/BasicTestDataBehaviour.php` | `src/TestCaseBase/BasicTestDataBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/CacheTestBehaviour.php` | `src/TestCaseBase/CacheTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/CountryAddToSalesChannelTestBehaviour.php` | `src/TestCaseBase/CountryAddToSalesChannelTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/DatabaseTransactionBehaviour.php` | `src/TestCaseBase/DatabaseTransactionBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/EnvTestBehaviour.php` | `src/TestCaseBase/EnvTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/EventDispatcherBehaviour.php` | `src/TestCaseBase/EventDispatcherBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/EventDispatcherWrapper.php` | `src/TestCaseBase/EventDispatcherWrapper.php` |
| `src/Core/Framework/Test/TestCaseBase/FilesystemBehaviour.php` | `src/TestCaseBase/FilesystemBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/IntegrationTestBehaviour.php` | `src/TestCaseBase/IntegrationTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/KernelLifecycleManager.php` | `src/TestCaseBase/KernelLifecycleManager.php` |
| `src/Core/Framework/Test/TestCaseBase/KernelTestBehaviour.php` | `src/TestCaseBase/KernelTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/MailTemplateTestBehaviour.php` | `src/TestCaseBase/MailTemplateTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/QueueTestBehaviour.php` | `src/TestCaseBase/QueueTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/RequestStackTestBehaviour.php` | `src/TestCaseBase/RequestStackTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/SalesChannelApiTestBehaviour.php` | `src/TestCaseBase/SalesChannelApiTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/SalesChannelFunctionalTestBehaviour.php` | `src/TestCaseBase/SalesChannelFunctionalTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/SessionTestBehaviour.php` | `src/TestCaseBase/SessionTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/TaxAddToSalesChannelTestBehaviour.php` | `src/TestCaseBase/TaxAddToSalesChannelTestBehaviour.php` |
| `src/Core/Framework/Test/TestCaseBase/TranslationTestBehaviour.php` | `src/TestCaseBase/TranslationTestBehaviour.php` |

`IntegrationTestBehaviour` composes basic data, cache, transactions, filesystem, kernel, request stack, session, and translation behaviours. `AdminFunctionalTestBehaviour` adds Admin API support; `SalesChannelFunctionalTestBehaviour` adds Store API and country assignment support. Preserve these compositions and PHPUnit lifecycle hooks.

`KernelLifecycleManager` and `EventDispatcherWrapper` are supporting implementation classes in this directory, not consumer base traits. Retain that distinction when defining the supported public API.

## Complete base-helper inventory

All files in `src/Core/Framework/Test/TestCaseHelper/` are included in the extraction inventory:

| Existing file | Planned package file |
| --- | --- |
| `src/Core/Framework/Test/TestCaseHelper/AssertResponseHelper.php` | `src/TestCaseHelper/AssertResponseHelper.php` |
| `src/Core/Framework/Test/TestCaseHelper/CallableClass.php` | `src/TestCaseHelper/CallableClass.php` |
| `src/Core/Framework/Test/TestCaseHelper/ExtensionHelper.php` | `src/TestCaseHelper/ExtensionHelper.php` |
| `src/Core/Framework/Test/TestCaseHelper/ReflectionHelper.php` | `src/TestCaseHelper/ReflectionHelper.php` |
| `src/Core/Framework/Test/TestCaseHelper/StopWorkerWhenIdleListener.php` | `src/TestCaseHelper/StopWorkerWhenIdleListener.php` |
| `src/Core/Framework/Test/TestCaseHelper/StoreApiSessionListener.php` | `src/TestCaseHelper/StoreApiSessionListener.php` |
| `src/Core/Framework/Test/TestCaseHelper/TestBrowser.php` | `src/TestCaseHelper/TestBrowser.php` |
| `src/Core/Framework/Test/TestCaseHelper/TestUser.php` | `src/TestCaseHelper/TestUser.php` |

## Required supporting infrastructure

| Source | Required capability |
| --- | --- |
| `src/Core/Framework/Test/TestKernel.php`, `TestBundle.php` | Test kernel and bundle setup |
| `src/Core/Framework/Test/RemoveDeprecatedServicesPass.php`, `DependencyInjection/CompilerPass/ContainerVisibilityCompilerPass.php` | Test container compilation |
| `src/Core/Framework/Test/TestCacheClearer.php` | Cache cleanup used by the base behaviours |
| `src/Core/Framework/Test/TestSessionStorage.php`, `TestSessionStorageFactory.php` | Test session services |
| `src/Core/Framework/Test/Filesystem/Adapter/MemoryAdapterFactory.php` | In-memory filesystems and cleanup |
| `src/Core/Framework/DependencyInjection/services_test.php` | Browser, filesystem, cache, session, and other test service wiring |
| `src/Core/Framework/Resources/config/packages/test/` | Framework and Shopware test configuration |
| `src/Core/Test/TestDefaults.php` | Fixture identifiers referenced by base behaviours |
| `src/Core/Test/PHPUnit/CompletionGuard/` | Detection of premature PHPUnit termination |
| `src/Core/Test/PHPUnit/Extension/` and `src/Core/Test/Annotation/DisabledFeatures.php` | Feature flags, database-diff checks, and optional Datadog reporting |

Extract the service definitions needed by reusable testing infrastructure. The existing test service file also registers core-specific DAL fixtures and payment handlers; importing the entire file would couple consumers to the platform’s own test suite.

## Additional shared testing scope

- `src/Core/Test/Stub/`: reusable DAL repositories, connections, event dispatchers, message buses, and other test doubles.
- `src/Core/Test/Integration/`, `Generator.php`, `FixtureLoader.php`, and `TestBuilderTrait.php`: fixture builders, integration helpers, and fixture loading.
- `src/Core/Test/Assert/` and `Constraint/`: reusable assertions and constraints.
- `src/Core/Framework/Test/Plugin/` and `Migration/MigrationTestBehaviour.php`: plugin and migration testing.
- `src/Core/Test/AppSystemTestBehaviour.php` and shared helpers under `src/Core/Framework/Test/`: app, store, SEO, and other integration support.
- `src/Storefront/Test/` and `src/Elasticsearch/Test/`: optional component-specific behaviours with separately verified component compatibility.

Domain-specific fixtures and abstract bases within the platform’s own `tests/` tree require individual review before promotion to a public package API.

## Compatibility and extraction rules

Use the package’s own namespace and implementation. Wrapping or extending the old core test utilities would leave fixes and features dependent on the installed core version. Do not shadow `Shopware\Core\` through Composer autoloading or copy production core classes into this package.

When integration code is implemented, declare an explicit supported core range based on tested versions, independently of the package version. Do not use `self.version` or claim that `*` provides compatibility. Add direct Symfony, Doctrine, and other library requirements for APIs used by the extracted code. No supported core range has been established by this inventory.

Kernel factories, plugin loaders, service identifiers, DAL types, schema-dependent fixture setup, feature flags, and static-analysis kernels are the main version-sensitive boundaries. Isolate only the differences demonstrated by supported versions; avoid speculative version adapters.

Consumers must be able to migrate to the new namespace while existing core testing classes continue to work. Any later core-side forwarding or deprecation requires its own compatibility tests and release documentation.

## Validation required during implementation

- Port existing focused tests for `TestBootstrapper`, environment handling, reflection helpers, filesystem factories, kernel lifecycle, transactions, queues, and PHPUnit extensions.
- Test project discovery and plugin development autoloading in both a platform checkout and a consuming Composer project.
- Verify installation and plugin activation against an isolated test database, including failure paths.
- Verify transaction rollback, kernel resets, and cleanup of cache, filesystem, sessions, events, requests, and translations between tests.
- Exercise authenticated Admin API and Store API requests using the new browser and base behaviours.
- Test the completion guard in a subprocess so an intentional early exit cannot terminate the parent test runner.
- Run a compatibility matrix across every advertised Shopware and PHPUnit version range before publishing.
