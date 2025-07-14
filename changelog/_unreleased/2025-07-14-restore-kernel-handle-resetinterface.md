### Changed

- Removed the overridden `Kernel::handle()` method in `Shopware\Core\Kernel` to allow Symfony's default logic to call `services_resetter`.
- This enables support for `ResetInterface` and proper service resetting in long-running runtimes like FrankenPHP or RoadRunner.
- The custom plugin initialization logic has been retained inside `Kernel::boot()`, without interfering with Symfony's internal flags and lifecycle.

Fixes: [#11215](https://github.com/shopware/shopware/issues/11215)
