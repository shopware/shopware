/**
 * @sw-package framework
 */
import ViewAdapter from 'src/core/adapter/view.adapter';
import { createI18n } from 'vue-i18n';
import type { FallbackLocale, I18n } from 'vue-i18n';
import type { Router } from 'vue-router';
import { createApp, defineAsyncComponent, h } from 'vue';
import type { Component as VueComponent, App } from 'vue';
import VuePlugins from 'src/app/plugin';
import setupShopwareDevtools from 'src/app/adapter/view/sw-vue-devtools';
import type ApplicationBootstrapper from 'src/core/application';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type { ComponentPublicInstance } from '@vue/runtime-core';

import * as MeteorImport from '@shopware-ag/meteor-component-library';
import getBlockDataScope from '../../component/structure/sw-block-override/sw-block/get-block-data-scope';
import useSystem from '../../composables/use-system';
import useSession from '../../composables/use-session';

const { Component, State, Mixin } = Shopware;

/**
 * @private
 */
export default class VueAdapter extends ViewAdapter {
    private resolvedComponentConfigs: Map<string, Promise<ComponentConfig | boolean>>;

    private vueComponents: {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        [componentName: string]: VueComponent<any, any, any, any>;
    };

    private i18n?: I18n;

    public app: App<Element>;

    constructor(Application: ApplicationBootstrapper) {
        super(Application);

        this.i18n = undefined;
        this.resolvedComponentConfigs = new Map();
        this.vueComponents = {};
        this.app = createApp({
            name: 'ShopwareAdministration',
            template: '<sw-admin />',
        });
    }

    /**
     * Creates the main instance for the view layer.
     * Is used on startup process of the main application.
     */
    init(renderElement: string, router: Router, providers: { [key: string]: unknown }): App<Element> {
        return this.initVue(renderElement, router, providers);
    }

    initVue(renderElement: string, router: Router, providers: { [key: string]: unknown }): App<Element> {
        this.initPlugins();
        this.initDirectives();

        const vuexRoot = State._store;
        // eslint-disable-next-line @typescript-eslint/no-empty-object-type
        const i18n = this.initLocales();

        // add router to View
        this.router = router;
        // add i18n to View
        this.i18n = i18n;

        if (!this.app) {
            throw new Error('Vue app is not initialized yet');
        }

        function fixI18NParametersOrder(args: Parameters<typeof i18n.global.t>): Parameters<typeof i18n.global.t> {
            if (args.length === 3 && typeof args[1] === 'number' && typeof args[2] === 'object') {
                console.warn(
                    'the order of the parameters for $t has changed in the latest version.',
                    'Please, check Vue I18n documentation for more details:',
                    // eslint-disable-next-line max-len
                    'https://vue-i18n.intlify.dev/guide/migration/breaking10#tc-key-key-resourcekeys-choice-number-named-record-string-unknown-translateresult',
                );
                // This is a workaround to avoid breaking changes for the $tc function which that swap the second and
                // third parameters in the latest version.
                return [
                    args[0],
                    args[1],
                    args[2],
                ];
            }
            return args;
        }

        this.app.config.compilerOptions.whitespace = 'preserve';
        this.app.config.performance = process.env.NODE_ENV !== 'production';
        this.app.config.globalProperties.$t = function (...args: Parameters<typeof i18n.global.t>) {
            return i18n.global.t(...fixI18NParametersOrder(args));
        } as typeof i18n.global.t;
        /**
         * @deprecated tag:v6.8.0 - Will be removed, use $t instead.
         */
        this.app.config.globalProperties.$tc = function (...args: Parameters<typeof i18n.global.t>) {
            if (window._features_.V6_8_0_0) {
                console.warn(
                    'Deprecation Warning',
                    'The $tc function is deprecated and will be removed in future versions. Please use $t instead.',
                );
            }
            return i18n.global.t(...fixI18NParametersOrder(args));
        } as typeof i18n.global.t;

        this.app.config.warnHandler = (msg: string, instance: unknown, trace: string) => {
            const warnArgs = [
                `[Vue warn]: ${msg}`,
                trace,
                instance,
            ];

            console.warn(...warnArgs);

            if (msg.includes('Template compilation error')) {
                console.error(
                    ...[
                        `[Vue error]: ${msg}`,
                        trace,
                        instance,
                    ],
                );
                throw new Error(msg);
            }
        };

        // This is a hack for providing the data scope to the components.
        Object.defineProperty(this.app.config.globalProperties, '$dataScope', {
            get: getBlockDataScope,
            enumerable: true,
        });

        /**
         * This is a hack for providing the services to the components.
         * We shouldn't use this anymore because it is not supported well
         * in Vue3 (because the services are lazy loaded).
         *
         * So we should convert from provide/inject to Shopware.Service
         */
        Object.keys(providers).forEach((provideKey) => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            Object.defineProperty(this.app._context.provides, provideKey, {
                get: () => providers[provideKey],
                enumerable: true,
                configurable: true,
                // eslint-disable-next-line @typescript-eslint/no-empty-function
                set() {},
            });
        });

        this.root = this.app;

        this.app.use(router);
        this.app.use(vuexRoot);
        this.app.use(i18n);

        // This is a hack for providing the i18n scope to the components.
        Object.defineProperty(this.app.config.globalProperties, '$i18n', {
            get: () => {
                return i18n.global;
            },
            enumerable: true,
        });

        // Add global properties to root view instance
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.$tc = i18n.global.t;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.$t = i18n.global.t;

        this.initTitle(this.app);
        /* eslint-enable max-len */

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        this.app.mount(renderElement);

        if (process.env.NODE_ENV === 'development') {
            setupShopwareDevtools(this.root);
        }

        return this.root;
    }

    /**
     * Initialize of all dependencies.
     */
    async initDependencies() {
        const initContainer = this.Application.getContainer('init');

        // make specific components synchronous
        const syncComponents = [
            'sw-admin',
            'sw-admin-menu',
            'sw-button',
            'sw-button-process',
            'sw-card',
            'sw-card-section',
            'sw-card-view',
            'sw-container',
            'sw-desktop',
            'sw-empty-state',
            'sw-entity-listing',
            'sw-entity-multi-select',
            'sw-entity-multi-id-select',
            'sw-entity-single-select',
            'sw-error-boundary',
            'sw-extension-component-section',
            'sw-ignore-class',
            'sw-loader',
            'sw-modal',
            'sw-multi-select',
            'sw-notification-center',
            'sw-notifications',
            'sw-page',
            'sw-router-link',
            'sw-search-bar',
            'sw-select-result',
            'sw-single-select',
            'sw-skeleton',
            'sw-skeleton-bar',
            'sw-tabs',
            'sw-tabs-item',
            'sw-version',
            /**
             * Quickfix for modules with refs and sync behavior.
             * They should be removed from the list in the future
             * when their async problems got fixed.
             */
            'sw-sales-channel-products-assignment-single-products',
            'sw-sales-channel-product-assignment-categories',
            'sw-sales-channel-products-assignment-dynamic-product-groups',
            'sw-upload-listener',
            'sw-media-list-selection-v2',
            'sw-media-list-selection-item-v2',
            'sw-settings-document-detail',
            'sw-settings-product-feature-sets-detail',
            'sw-system-config',
            'sw-settings-search-searchable-content',
            // base
            'sw-address',
            'sw-alert',
            'sw-alert-deprecated',
            'sw-avatar',
            'sw-avatar-deprecated',
            'sw-button-deprecated',
            'sw-button-group',
            'sw-card-deprecated',
            'sw-card-filter',
            'sw-chart',
            'sw-chart-card',
            'sw-circle-icon',
            'sw-collapse',
            'sw-description-list',
            'sw-error-summary',
            'sw-help-text',
            'sw-highlight-text',
            'sw-icon',
            'sw-icon-deprecated',
            'sw-inheritance-switch',
            'sw-label',
            'sw-price-preview',
            'sw-product-image',
            'sw-product-variant-info',
            'sw-property-search',
            'sw-radio-panel',
            'sw-rating-stars',
            'sw-simple-search-field',
            'sw-sorting-select',
            'sw-tabs-deprecated',
            'sw-user-card',
            // form
            'sw-url-field',
            'sw-textarea-field-deprecated',
            'sw-textarea-field',
            'sw-text-field-deprecated',
            'sw-text-field',
            'sw-text-editor',
            'sw-text-editor-toolbar-table-button',
            'sw-text-editor-toolbar-button',
            'sw-text-editor-toolbar',
            'sw-text-editor-table-toolbar',
            'sw-text-editor-link-menu',
            'sw-tagged-field',
            'sw-switch-field',
            'sw-snippet-field-edit-modal',
            'sw-snippet-field',
            'sw-select-rule-create',
            'sw-select-option',
            'sw-select-field-deprecated',
            'sw-select-field',
            'sw-radio-field',
            'sw-purchase-price-field',
            'sw-price-field',
            'sw-password-field',
            'sw-number-field',
            'sw-maintain-currencies-modal',
            'sw-list-price-field',
            'sw-gtc-checkbox',
            'sw-form-field-renderer',
            'sw-file-input',
            'sw-field-copyable',
            'sw-email-field',
            'sw-dynamic-url-field',
            'sw-custom-field-set-renderer',
            'sw-confirm-field',
            'sw-colorpicker-deprecated',
            'sw-colorpicker',
            'sw-checkbox-field-deprecated',
            'sw-checkbox-field',
            'sw-boolean-radio-group',
            'sw-entity-single-select',
            'sw-entity-multi-select',
            'sw-entity-multi-id-select',
            'sw-entity-many-to-many-select',
            'sw-entity-advanced-selection-modal',
            'sw-advanced-selection-rule',
            'sw-advanced-selection-product',
            'sw-single-select',
            'sw-select-selection-list',
            'sw-select-result-list',
            'sw-select-result',
            'sw-select-base',
            'sw-multi-tag-select',
            'sw-multi-select',
            'sw-field-error',
            'sw-contextual-field',
            'sw-block-field',
            'sw-base-field',
            'sw-code-editor',
            'sw-datepicker',
            'sw-datepicker-deprecated',
            'sw-url-field-deprecated',
            'sw-switch-field-deprecated',
            'sw-select-number-field',
            'sw-password-field-deprecated',
            'sw-number-field-deprecated',
            'sw-email-field-deprecated',
            'sw-compact-colorpicker',
            'sw-entity-tag-select',
            'sw-entity-advanced-selection-modal-grid',
            'sw-multi-tag-ip-select',
            'sw-grouped-single-select',
            'sw-price-preview',
            'sw-context-menu',
            'sw-context-menu-item',
        ];

        syncComponents.forEach((componentName) => {
            Component.markComponentAsSync(componentName);
        });

        // initialize all components
        await this.initComponents();

        // initialize all module locales
        this.initModuleLocales();

        // initialize all module routes
        const allRoutes = this.applicationFactory.module.getModuleRoutes();
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        initContainer.router.addModuleRoutes(allRoutes);

        // create routes for core and plugins
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        initContainer.router.createRouterInstance();
    }

    /**
     * Initializes all core components as Vue components.
     */
    async initComponents() {
        const componentRegistry = this.componentFactory.getComponentRegistry();
        this.componentFactory.resolveComponentTemplates();

        const initializedComponents = [...componentRegistry.keys()].map((name) => {
            return this.createComponent(name);
        });

        await Promise.all(initializedComponents);

        /**
         * Initialize all meteor components
         */
        const meteorComponents: (keyof typeof MeteorImport)[] = [
            'MtBanner',
            'MtLoader',
            'MtProgressBar',
            'MtButton',
            'MtCheckbox',
            'MtColorpicker',
            'MtEmailField',
            'MtEmptyState',
            'MtNumberField',
            'MtPasswordField',
            'MtSelect',
            'MtSlider',
            'MtSwitch',
            'MtTextField',
            'MtTextarea',
            'MtIcon',
            'MtDataTable',
            'MtPagination',
            'MtSkeletonBar',
            'MtToast',
            'MtFloatingUi',
            'MtPopover',
            'MtTextEditorToolbarButton',
            'MtModal',
            'MtModalRoot',
            'MtModalClose',
            'MtModalTrigger',
            'MtModalAction',
            'MtUrlField',
            'MtSearch',
            'MtLink',
            'MtUnitField',
        ];

        meteorComponents.forEach((componentName) => {
            const componentNameAsKebabCase = Shopware.Utils.string.kebabCase(componentName);
            this.app.component(componentNameAsKebabCase, MeteorImport[componentName]);
        });

        return this.vueComponents;
    }

    /**
     * Initializes all core components as Vue components.
     */
    initModuleLocales() {
        // Extend default snippets with module specific snippets
        const moduleSnippets = this.applicationFactory.module.getModuleSnippets();

        Object.entries(moduleSnippets).forEach(
            ([
                key,
                moduleSnippet,
            ]) => {
                this.applicationFactory.locale.extend(key, moduleSnippet);
            },
        );

        return this.applicationFactory.locale;
    }

    /**
     * Returns the component as a Vue component.
     * Includes the full rendered template with all overrides.
     */
    createComponent(componentName: string): Promise<App<Element>> {
        return new Promise((resolve) => {
            // load sync components directly
            if (Component.isSyncComponent && Component.isSyncComponent(componentName)) {
                const resolvedComponent = this.componentResolver(componentName);

                if (resolvedComponent === undefined) {
                    return;
                }

                void resolvedComponent.then((component) => {
                    let vueComponent;

                    if (typeof component !== 'boolean') {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                        this.app?.component(componentName, component);
                        vueComponent = this.app?.component(componentName);
                    }

                    // @ts-expect-error - resolved config does not match completely a standard vue component
                    this.vueComponents[componentName] = vueComponent;
                    resolve(vueComponent as unknown as App<Element>);
                });

                return;
            }

            // load async components
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.app?.component(
                componentName,
                defineAsyncComponent({
                    // the loader function
                    // @ts-expect-error - resolved config does not match completely a standard vue component
                    loader: () => this.componentResolver(componentName),
                    // Delay before showing the loading component. Default: 200ms.
                    delay: 0,
                    loadingComponent: {
                        name: 'async-loading-component',
                        inheritAttrs: false,
                        render() {
                            return h('div', {
                                style: { display: 'none' },
                            });
                        },
                    },
                }),
            );

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
            const vueComponent = this.app?.component(componentName);

            // @ts-expect-error - resolved config does not match completely a standard vue component
            this.vueComponents[componentName] = vueComponent;

            resolve(vueComponent as unknown as App<Element>);
        });
    }

    componentResolver(componentName: string) {
        if (!this.resolvedComponentConfigs.has(componentName)) {
            this.resolvedComponentConfigs.set(
                componentName,
                new Promise((resolve) => {
                    void Component.build(componentName).then((componentConfig) => {
                        if (typeof componentConfig === 'boolean') {
                            resolve(false);
                        } else {
                            this.resolveMixins(componentConfig);
                        }

                        resolve(componentConfig);
                    });
                }),
            );
        }

        return this.resolvedComponentConfigs.get(componentName);
    }

    /**
     * Builds and creates a Vue component using the provided component configuration.
     */
    buildAndCreateComponent(componentConfig: ComponentConfig) {
        if (!componentConfig.name) {
            throw new Error('Component name is missing');
        }

        const componentName = componentConfig.name;
        this.resolveMixins(componentConfig);

        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        this.app?.component(componentName, componentConfig);
        const vueComponent = this.app?.component(componentName);

        // @ts-expect-error - resolved config does not match completely a standard vue component
        this.vueComponents[componentName] = vueComponent;

        return vueComponent;
    }

    /**
     * Returns a final Vue component by its name.
     */
    getComponent(componentName: string) {
        if (!this.vueComponents[componentName]) {
            return null;
        }

        return this.vueComponents[componentName] as App<Element>;
    }

    /**
     * Returns a final Vue component by its name without defineAsyncComponent
     * which cannot be used in the router.
     */
    getComponentForRoute(componentName: string) {
        return () => this.componentResolver(componentName);
    }

    /**
     * Returns the complete set of available Vue components.
     */
    // @ts-expect-error - resolved config for each component does not match completely a standard vue component
    getComponents() {
        return this.vueComponents;
    }

    /**
     * Returns the adapter wrapper
     */
    getWrapper() {
        return this.app;
    }

    /**
     * Returns the name of the adapter
     */
    getName(): string {
        return 'Vue.js';
    }

    /**
     * Returns the Vue.set function
     */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    setReactive(this: void, target: any, propertyName: string, value: unknown) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        target[propertyName] = value;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-member-access
        return target[propertyName];
    }

    /**
     * Returns the Vue.delete function
     */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    deleteReactive(target: any, propertyName: string) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        delete target[propertyName];
    }

    /**
     * Private methods
     */

    /**
     * Initialises all plugins for VueJS
     *
     * @private
     */
    initPlugins() {
        VuePlugins.forEach((plugin) => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (plugin?.install?.installed) {
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-argument
            this.app?.use(plugin);
        });

        return true;
    }

    /**
     * Initializes all custom directives.
     *
     * @private
     */
    initDirectives() {
        const registry = this.Application.getContainer('factory').directive.getDirectiveRegistry();

        registry.forEach((directive, name) => {
            this.app?.directive(name, directive);
        });

        return true;
    }

    /**
     * Initialises the standard locales.
     */
    initLocales() {
        const registry = this.localeFactory.getLocaleRegistry();
        const messages = {};
        const fallbackLocale = Shopware.Context.app.fallbackLocale as FallbackLocale;
        const { registerAdminLocale } = useSystem();

        registry.forEach((localeMessages, key) => {
            registerAdminLocale(key);
            // @ts-expect-error - key is safe because we iterate through the registry
            messages[key] = localeMessages;
        });

        const lastKnownLocale = this.localeFactory.getLastKnownLocale();
        void useSession().setAdminLocale(lastKnownLocale);

        const options = {
            legacy: false,
            locale: lastKnownLocale,
            fallbackLocale,
            silentFallbackWarn: true,
            sync: true,
            messages,
            allowComposition: true,
        } as const;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        const i18n = createI18n(options);

        Shopware.Vue.watch(
            useSession().currentLocale,
            (currentLocale: string | null) => {
                i18n.global.locale.value = currentLocale ?? '';
            },
            { immediate: true },
        );

        this.setLocaleFromUser();

        // watch for changes of the user to update the locale
        Shopware.Vue.watch(
            useSession().currentUser,
            (newValue, oldValue) => {
                const currentUserLocaleId = newValue?.localeId;
                const oldUserLocaleId = oldValue?.localeId;

                if (currentUserLocaleId && currentUserLocaleId !== oldUserLocaleId) {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                    Shopware.Service('localeHelper').setLocaleWithId(currentUserLocaleId);
                }
            },
            { deep: true },
        );

        return i18n;
    }

    setLocaleFromUser() {
        const currentUser = useSession().currentUser.value;

        if (currentUser) {
            const userLocaleId = currentUser.localeId;
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            Shopware.Service('localeHelper').setLocaleWithId(userLocaleId);
        }
    }

    /**
     * Extends Vue prototype to access $createTitle function
     *
     * @private
     */
    initTitle(app: App<Element>) {
        app.config.globalProperties.$createTitle = function createTitle(
            this: ComponentPublicInstance,
            identifier: string | null = null,
            ...additionalParams
        ): string {
            if (!this.$root) {
                return '';
            }

            const baseTitle = this.$root.$tc('global.sw-admin-menu.textShopwareAdmin');

            if (!this.$route.meta || !this.$route.meta.$module) {
                return '';
            }

            // @ts-expect-error - $module is not typed correctly
            const moduleTitle = this.$route.meta.$module?.title as string;
            const pageTitle = this.$root.$tc(moduleTitle);

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const params = [
                baseTitle,
                pageTitle,
                identifier,
                ...additionalParams,
            ].filter((item) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                return item !== null && item.trim() !== '';
            });

            return params.reverse().join(' | ');
        };
    }

    /**
     * Recursively resolves mixins referenced by name
     *
     * @private
     */
    resolveMixins(componentConfig: ComponentConfig) {
        // If the mixin is a string, use our mixin registry
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        if (componentConfig.mixins?.length) {
            // eslint-disable-next-line max-len
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
            componentConfig.mixins = componentConfig.mixins.map((mixin) => {
                if (typeof mixin === 'string') {
                    // @ts-expect-error
                    return Mixin.getByName(mixin);
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                return mixin;
            });
        }

        if (componentConfig.extends) {
            // @ts-expect-error - extends can be a string or a component config
            this.resolveMixins(componentConfig.extends);
        }
    }
}
