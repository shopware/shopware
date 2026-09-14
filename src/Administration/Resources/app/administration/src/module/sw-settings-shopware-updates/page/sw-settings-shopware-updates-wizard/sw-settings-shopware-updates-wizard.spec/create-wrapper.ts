/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

/** @private */
export default async function createWrapper(updateServiceOverrides = {}) {
    return mount(
        await wrapTestComponent('sw-settings-shopware-updates-wizard', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    updateService: {
                        checkForUpdates: () =>
                            Promise.resolve({
                                extensions: [],
                                title: 'Release 6.4.18.0',
                                body: 'This is a test release',
                                date: '2022-12-08T09:04:06.000+00:00',
                                version: '6.4.18.0',
                                fixedVulnerabilities: [],
                            }),
                        checkLicense: () =>
                            Promise.resolve({
                                isValid: false,
                            }),
                        deactivateExtensions: () => {
                            const error: Error & { response?: unknown } = new Error();

                            error.response = {
                                data: {
                                    errors: [
                                        {
                                            code: 'THEME__THEME_ASSIGNMENT',
                                            meta: {
                                                parameters: {
                                                    themeName: '7305fd18-09ee-4d2c-afd4-b9fb90ad8508',
                                                    assignments: 'afe95e1e-cc8e-487b-863a-94c5c4e51fa6',
                                                },
                                            },
                                        },
                                    ],
                                },
                            };

                            return Promise.reject(error);
                        },
                        extensionCompatibility: () => Promise.resolve([]),
                        downloadRecovery: () => Promise.resolve([]),
                        ...updateServiceOverrides,
                    },
                },
                mocks: {
                    $route: {
                        name: '',
                        meta: {
                            parentPath: 'sw.settings.index',
                            $module: {
                                type: 'core',
                                name: 'settings',
                                title: 'sw-settings.general.mainMenuItemGeneral',
                                color: '#9AA8B5',
                                icon: 'default-action-settings',
                                favicon: 'icon-module-settings.svg',
                                routes: {
                                    index: {
                                        path: '/sw/settings/index',
                                        icon: 'default-action-settings',
                                        name: 'sw.settings.index',
                                        type: 'core',
                                        components: {
                                            default: {
                                                _custom: {
                                                    type: 'function',
                                                    display: '<span>ƒ</span> VueComponent(options)',
                                                },
                                            },
                                        },
                                        isChildren: false,
                                        routeKey: 'index',
                                    },
                                },
                                navigation: [
                                    {
                                        id: 'sw-settings',
                                        label: 'sw-settings.general.mainMenuItemGeneral',
                                        color: '#9AA8B5',
                                        icon: 'default-action-settings',
                                        path: 'sw.settings.index',
                                        position: 80,
                                        children: [],
                                    },
                                ],
                            },
                        },
                        params: {
                            id: '',
                        },
                    },
                    $i18n: {
                        locale: 'de-De',
                    },
                },
                stubs: {
                    'sw-page': await wrapTestComponent('sw-page'),
                    'sw-search-bar': {
                        template: '<div></div>',
                    },
                    'sw-notification-center': {
                        template: '<div></div>',
                    },
                    'sw-help-center': true,
                    'sw-tooltip': {
                        template: '<div></div>',
                    },
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-ignore-class': true,
                    'sw-settings-shopware-updates-extensions': {
                        template: '<div class="sw-settings-shopware-updates-extensions"></div>',
                    },
                    'sw-loader': {
                        template: '<div></div>',
                    },
                    'router-link': {
                        template: '<a></a>',
                    },
                    'sw-app-actions': true,
                    'sw-extension-component-section': true,
                    'sw-error-summary': true,
                    'sw-modal': {
                        template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'mt-banner': true,
                    'sw-external-link': {
                        template: '<a class="sw-external-link" :href="$attrs.href"><slot></slot></a>',
                    },
                    'mt-progress-bar': true,
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', {
                        sync: true,
                    }),
                    'sw-field-error': true,
                    'sw-base-field': true,
                    'sw-app-topbar-button': true,
                    'sw-app-topbar-sidebar': true,
                    'sw-help-center-v2': true,
                    'sw-empty-state': true,

                    'sw-radio-field': true,
                    'sw-ai-copilot-badge': true,
                    'sw-provide': true,
                },
            },
            attachTo: document.body,
        },
    );
}
