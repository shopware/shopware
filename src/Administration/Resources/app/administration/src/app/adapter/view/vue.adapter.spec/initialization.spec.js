/**
 * @sw-package framework
 */

import VueAdapter from 'src/app/adapter/view/vue.adapter';
import Feature from 'src/core/feature';
import LocaleFactory from 'src/core/factory/locale.factory';
import DirectiveFactory from 'src/core/factory/directive.factory';
import FilterFactory from 'src/core/factory/filter.factory';
import VueRouter from 'vue-router';
import AsyncComponentFactory from 'src/core/factory/async-component.factory';
import ModuleFactory from 'src/core/factory/module.factory';
import initializeRouter from 'src/app/init/router.init';
import setupShopwareDevtools from 'src/app/adapter/view/sw-vue-devtools';
import { defineComponent } from 'vue';
import { createApplication, prepareVueAdapterEnvironment, resetVueAdapterTestState } from './vue-adapter-test-setup';

jest.mock('src/app/adapter/view/sw-vue-devtools', () => {
    return jest.fn();
});

describe('ASYNC app/adapter/view/vue.adapter.js - initialization', () => {
    let application;
    let vueAdapter;
    let rootComponent;

    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (_, msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes('plugin is already installed');
            },
        });

        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes('plugin must either be a function');
            },
        });
    });

    beforeEach(async () => {
        prepareVueAdapterEnvironment();
        process.env.NODE_ENV = 'development';

        application = createApplication()
            .addFactory('locale', () => {
                return LocaleFactory;
            })
            .addFactory('directive', () => {
                return DirectiveFactory;
            })
            .addFactory('filter', () => {
                return FilterFactory;
            })
            .addFactory('component', () => {
                return AsyncComponentFactory;
            })
            .addFactory('module', () => {
                return ModuleFactory;
            });

        application.addInitializer('router', initializeRouter);

        const locale = Shopware.Application.getContainer('factory').locale;
        if (!locale.getLocaleByName('en-GB')) {
            locale.register('en-GB', {
                global: {
                    'sw-admin-menu': {
                        textShopwareAdmin: 'Text Shopware Admin',
                    },
                    my: {
                        mock: {
                            title: 'Mock title',
                        },
                    },
                },
            });
        }

        if (!Shopware.Filter.getByName('my-mock-filter')) {
            Shopware.Filter.register('my-mock-filter', () => {
                return 'mocked';
            });
        }

        if (!Shopware.Directive.getByName('my-mock-directive')) {
            Shopware.Directive.register('my-mock-directive', () => {
                return {
                    bind() {},
                    inserted() {},
                    update() {},
                    componentUpdated() {},
                    unbind() {},
                };
            });
        }

        // create vueAdapter with custom application
        vueAdapter = new VueAdapter(application);

        // create router
        const router = VueRouter.createRouter({
            history: VueRouter.createWebHashHistory(),
            routes: [
                {
                    path: '/',
                    component: defineComponent({
                        template: '<sw-admin></sw-admin>',
                    }),
                },
            ],
        });

        // add main component
        if (!Shopware.Component.getComponentRegistry().has('sw-admin')) {
            Shopware.Component.register('sw-admin', {
                template: '<div class="sw-admin"></div>',
            });
        }

        // add VueAdapter to Shopware object
        Shopware.Application.setViewAdapter(vueAdapter);

        await vueAdapter.initDependencies();

        // create div with id app
        document.body.innerHTML = '<div id="app"></div>';

        rootComponent = vueAdapter.init('#app', router, {});
    });

    afterEach(() => {
        rootComponent.unmount();
        rootComponent = undefined;
        resetVueAdapterTestState();
    });

    it('should initialize the plugins correctly', async () => {
        // check if all plugins are registered correctly
        expect(rootComponent.config.globalProperties.$router).toBeDefined();
        expect(rootComponent.config.globalProperties.$tc).toBeDefined();
        expect(rootComponent.config.globalProperties.$store).toBeDefined();
        expect(rootComponent.config.globalProperties.$dataScope).toBeDefined();
        expect(rootComponent.config.globalProperties.$swLegacyBlockIf).toBeDefined();
        expect(rootComponent.config.globalProperties.$swLegacyBlockElseIf).toBeDefined();
        expect(rootComponent.config.globalProperties.$swLegacyBlockElse).toBeDefined();
    });

    it('should use the translation wrappers for components, the root instance and Shopware.Snippet', () => {
        const deprecationSpy = jest.spyOn(Feature, 'triggerDeprecationOrThrow').mockImplementation(() => {});
        vueAdapter.i18n.global.mergeLocaleMessage(vueAdapter.i18n.global.locale.value, {
            'sw-vue-adapter-test': {
                items: 'no items | one item for {name} | {count} items for {name}',
            },
        });
        const key = 'sw-vue-adapter-test.items';
        const { $t, $tc } = rootComponent.config.globalProperties;

        expect($t).not.toBe(vueAdapter.i18n.global.t);
        expect(rootComponent.$t).toBe($t);
        expect(rootComponent.$tc).toBe($tc);
        /* eslint-disable sw-core-rules/no-tc-translation */
        expect($t(key, 1, { name: 'Ada' })).toBe('one item for Ada');
        expect($tc(key, { name: 'Ada' }, 2)).toBe('2 items for Ada');
        expect(Shopware.Snippet.t(key, 3, { name: 'Ada' })).toBe('3 items for Ada');
        expect(Shopware.Snippet.tc(key, { name: 'Ada' }, 2)).toBe('2 items for Ada');
        /* eslint-enable sw-core-rules/no-tc-translation */
        expect(deprecationSpy).toHaveBeenCalledWith(
            'V6_9_0_0',
            expect.stringContaining(`Replace Shopware.Snippet.tc('${key}') with Shopware.Snippet.t('${key}').`),
        );

        deprecationSpy.mockRestore();
    });

    it('should scope legacy block helpers by component instance', () => {
        const vmOne = { $: { uid: 1 } };
        const vmTwo = { $: { uid: 2 } };
        const firstCase = {
            segmentCaseIndex: 0,
            renderOrderSegment: 'defaultSlot',
            isStartingCondition: true,
        };
        const fallbackCase = {
            segmentCaseIndex: 1,
            renderOrderSegment: 'defaultSlot',
            isStartingCondition: false,
        };

        expect(rootComponent.config.globalProperties.$swLegacyBlockIf.call(vmOne, 'test-block', false, firstCase)).toBe(
            false,
        );
        expect(rootComponent.config.globalProperties.$swLegacyBlockElse.call(vmTwo, 'test-block', fallbackCase)).toBe(false);
        expect(rootComponent.config.globalProperties.$swLegacyBlockElse.call(vmOne, 'test-block', fallbackCase)).toBe(true);
    });

    it('should initialize the directives correctly', async () => {
        expect(rootComponent._context.directives['my-mock-directive']).toBeDefined();
    });

    it('should add the createTitle to the rootComponent', () => {
        expect(rootComponent.config.globalProperties.$createTitle).toBeDefined();
    });

    it('should have correct working createTitle method', () => {
        const result = rootComponent.config.globalProperties.$createTitle.call(
            {
                $root: {
                    $t: (v) => rootComponent.$t(v),
                },
                $route: {
                    meta: {
                        $module: {
                            title: 'global.my.mock.title',
                        },
                    },
                },
            },
            'Test',
        );

        expect(result).toBe('Test | Mock title | Text Shopware Admin');
    });

    it('should add the store to the rootComponent', () => {
        expect(rootComponent.config.globalProperties.$store).toBeDefined();
    });

    it('should add all components to the root component', () => {
        expect(rootComponent._context.components['sw-admin']).toBeDefined();
    });

    it('should register the Meteor Components', () => {
        const meteorComponents = [
            'mt-banner',
            'mt-loader',
            'mt-progress-bar',
            'mt-button',
            'mt-checkbox',
            'mt-colorpicker',
            'mt-email-field',
            'mt-number-field',
            'mt-password-field',
            'mt-select',
            'mt-switch',
            'mt-text-field',
            'mt-search',
            'mt-textarea',
            'mt-icon',
            'mt-data-table',
            'mt-pagination',
            'mt-skeleton-bar',
        ];

        meteorComponents.forEach((componentName) => {
            expect(rootComponent._context.components[componentName]).toBeDefined();
        });
    });

    it('should add the router to the rootComponent', () => {
        expect(rootComponent.config.globalProperties.$router).toBeDefined();
    });

    it('should setup the devtools in development environment', async () => {
        expect(setupShopwareDevtools).toHaveBeenCalled();
    });

    it('should return the wrapper', async () => {
        const wrapper = vueAdapter.getWrapper();
        expect(wrapper).toHaveProperty('use');
        expect(wrapper).toHaveProperty('config');
        expect(wrapper).toHaveProperty('component');
        expect(wrapper).toHaveProperty('directive');
        expect(wrapper).toHaveProperty('mount');
    });

    it('should return the adapter name', async () => {
        expect(vueAdapter.getName()).toBe('Vue.js');
    });

    it('should update the i18n global locale to update the locale in UI when the locale in the session store changes', async () => {
        const expectedLocale = 'de-DE';

        Shopware.Store.get('session').setAdminLocaleState({
            locales: ['en-GB', 'de-DE'],
            locale: expectedLocale,
            languageId: '12345678',
        });

        await flushPromises();

        expect(vueAdapter.i18n.global.locale.value).toEqual(expectedLocale);
    });

    it('should remove the loading indicator after vue is mounted', async () => {
        // it is difficult to actually test for removal because the js code is located at /app/administration/shared,
        // which is not included in the test environment.
        expect(window.removePageLoadingIndicator).toHaveBeenCalled();
    });
});
