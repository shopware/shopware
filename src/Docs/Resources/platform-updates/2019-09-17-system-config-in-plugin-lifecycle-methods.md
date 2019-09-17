[titleEn]: <>(System config in plugin lifecycle methods)

We've made the `SystemConfigService` public. This allows using the
system config service in all plugin lifecycle methods. For example, one
use case is setting default configuration values in the install method.

Example:

```php
use \Shopware\Core\System\SystemConfig\SystemConfigService;

public function install(InstallContext $context)
{
    /** @var SystemConfigService $systemConfig */
    $systemConfig = $this->container->get(SystemConfigService::class);
    $domain = $this->getName() . '.config.';

    $currentValue = $systemConfig->get($domain . 'foobar');
    if ($currentValue === null) {
        $systemConfig->set($domain . 'foobar', 'myDefaultValue');
    }
}

```