# 2020-08-12 - Implement app system inside platform

## Context

We need a different extension mechanism besides the well known plugins, that works in cloud and on-prem environments in the same way.
That's why we envisioned the app system, currently publicly available as a [plugin](https://github.com/shopware/app-system).
We were able to quickly develop the app system, gather feedback and iterate over ideas without being bound to the strict release workflow of the shopware platform.
That was the reason why we started working on the app system as a plugin in the first place.

Now as the app system matured, it is time to integrate the app system back into the platform.
This has the following benefits:
* It sends the signal to partners and potential app manufacturers that the app system is stable
* Partners / app manufacturers can rely on the shopware 6 release cycle and the according upgrade process
* Users don't need to install an extra plugin in order to use apps

### App system concept

The app system is designed to run in a multi tenant SaaS system as well as on premise systems. Because of that it has some limitations and some new extension points compared to the plugin system.
An app consists of a manifest file, containing meta data about the app, for example what extension points the app uses, etc. and optionally storefront customizations (templates, JS, CSS, snippets, theme configuration).

#### App system limitations

* No PHP code execution: PHP code execution cannot be allowed in a multi tenant system due to security reasons, 
as we cannot trust third party code, because of that we cannot run third party code on the shopware servers.
In the app system third party backend code has to run on third party servers, that communicate over the api with the shopware server.

* No general JS administration extensions: Extending the administration through custom JS like plugins cannot be allowed due to security reasons, too.
This is mainly due to the fact that the apps JS code should not run in the context of the systems current user and with his/her permissions (as administration extensions from plugins do), but in the context of the app with only the permissions that app was granted.
For this reason custom extension points are created (specifically Action-Buttons and Custom Modules), to allow extending the administration without running JS in the context of the JS administration application.

* Nothing that requires constant file access: In a cloud environment it is not possible to store individual (meaning per tenant) files directly on the local filesystem of the servers. 
It is possible though to store that information on more distant storages like S3, but that has whole different performance characteristics than storing files on disk.
Because of that it is not possible to allow constant file access (meaning reading files on every request for example).
That's the reason why the metadata associated with apps (the content of the manifest files) and the template changes are stored in the database of the shop.
Additionally, the storefront theme files (JS and CSS) only need to be accessed during theme compilation, after that they are directly served from the CDN, so no constant access to the source files is required.

#### App system extension points

* [Webhook](https://docs.shopware.com/en/shopware-platform-dev-en/app-system-guide/app-base-guide?category=shopware-platform-dev-en/app-system-guide#webhooks): An app can register to webhooks to be notified on a predefined URL if some events happen inside shopware.

* [Action-Button](https://docs.shopware.com/en/shopware-platform-dev-en/app-system-guide/app-base-guide?category=shopware-platform-dev-en/app-system-guide#buttons): An app can display extra buttons on selected detail & listing pages inside the administration and can perform custom actions for the selected entities.

* [Custom Modules](https://docs.shopware.com/en/shopware-platform-dev-en/app-system-guide/app-base-guide?category=shopware-platform-dev-en/app-system-guide#create-own-module): An app can display it's own UI inside the administration. This is done via iFrames that are embedded in the administration.

* [Custom Fields](https://docs.shopware.com/en/shopware-platform-dev-en/app-system-guide/app-base-guide?category=shopware-platform-dev-en/app-system-guide#custom-fields): An app can register it's own custom fields sets, that are displayed along the other custom fields inside the administration.

* [Storefront Customizations](https://docs.shopware.com/en/shopware-platform-dev-en/app-system-guide/app-examples-and-tutorials/create-own-theme?category=shopware-platform-dev-en/app-system-guide/app-examples-and-tutorials): An app should be able to customize the storefront in the same way a plugin does. This includes the theme system, custom twig templates and custom JS and CSS.
In regard to the theme system apps are treated the same way as plugins are, especially regarding the theme inheritance. Apps can be explicitly set in the inheritance chain via `@TechnicalAppName`, if they are not referenced directly they are part of the fallback `@Plugins` namespace.

Extension points may be added as new features of the app system, but we have to make sure that it does not violate one of the limitations mentioned above. Additionally, it needs to be taken into account that it's possible to deploy and run that feature in the cloud environment.

## Decision

We will migrate the existing app system from the [plugin](https://github.com/shopware/app-system) into the platform. The app system will be part of the core bundle, analogous to the plugin system.
It will be moved to the `Shopware\Core\Framework\App` namespace.

### Migration process

We will try to split up the migration into multiple MRs to ensure that code reviews can be made with the needed care. Therefore, the migration is split in multiple coherent parts that build on each other.
Where necessary the changes will be hidden behind a feature flag, so the changes are invisible to the user until the migration process is completely finished. 
In practice the need for feature flags can be minimized, because the app system currently does not come with an administrative UI and is only usable by CLI. 
By ensuring that we migrate the CLI commands to install and activate apps as the last step of the migration process we can make sure the changes we made previously don't have any visible effect without the need to use feature flags.

We will release a v0.2.0 of the app system before starting the migration. The app-system will be migrated with that feature set to the platform, and at that point new development in the plugin is stopped and will continue inside the platform after the migration is completed.
Apps that work with v0.2.0 of the app system plugin will work in the same way with the app system inside the platform, that means during the migration we won't introduce any breaking changes from the point of view of an app developer.
For a plugin developer, who extended the app system itself, this migration will likely be a breaking change, as we will change the internal structure of the app system (e.g. change PHP class names, name spaces, entity names, etc.).

After the migration process is finished we will release a v0.3.0 of the app system plugin. The sole purpose for this update is to migrate the app data from the old plugin data structures to the new platform data structures and make sure that apps that were previously installed (with the app system plugin) continue to work (with the app system in platform). 
This means that shops on a shopware version prior to the version, in which we will release the app system as part of the platform, can use the app system plugin in v0.2.0 to already use the app system in their shops.
Shops that are already on the version where the app system is included as part of the platform can start right away using apps and don't need the plugin. 
For shops that used apps with the app system plugin and then update to a platform version where the app system is included, need to update the app system plugin to v0.3.0 right after upgrading the platform version, so that the already installed apps continue to work, after that the plugin can safely be deleted and is not necessary anymore.
 
## Consequences

As a consequence the app system is considered stable after the migration process is completed.
That means we won't introduce any changes that may break existing apps in minor (6.3.x) or patch (6.3.0.x) versions.
This especially includes the following:

* Changes to the manifest schema:
    Having a strict schema for the manifest file has the two benefits that the manifest files can quickly be validated (e.g at developing time inside IDEs) and that app developers can use autocompletion features from IDEs, which greatly improves developer experience.
    However schema changes to an existing schema can only be made in a way that won't break existing apps (new stuff can be added, the schema can be loosened, but nothing can be removed or made stricter).
    For making more radical changes to the schema a new version of the schema can be introduced, but it must be ensured that apps have enough time to adapt to the new schema, that's why it is necessary to have a period in which both versions of the schema are supported.
    
* Changes to the format of outgoing request:
    We send multiple requests to third party app backends (e.g. during registration, triggering webhooks, triggering action-buttons, loading custom modules).    
    The format of this requests cannot be changed in a breaking way in minor (6.3.x) and patch (6.3.0.x) versions. 
    This means it is possible to add new parameters to the outgoing requests, but not to remove (or rename) existing parameters.
    If this is needed it needs to be made in a major version (6.3) and needs to be documented properly, so that app developers can adapt to the changes.

All changes and possible breaks from the point of view of an app developer will be documented in separate `App System` sections inside the changelog and upgrade files.

Additionally, the app system plugin will be deprecated after the migration process is finished and all further development will take place inside the platform.
