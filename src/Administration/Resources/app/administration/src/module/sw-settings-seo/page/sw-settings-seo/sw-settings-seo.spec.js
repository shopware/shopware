/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

const classes = {
    root: 'sw-page__main-content',
    cardView: 'sw-card-view',
    templateCard: 'sw-seo-url-template-card',
    systemConfig: 'sw-system-config',
    settingsCard: 'sw-card',
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-seo', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-page': await wrapTestComponent('sw-page'),
                    'sw-icon': true,
                    'sw-button': true,
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-seo-url-template-card': true,
                    'sw-system-config': await wrapTestComponent('sw-system-config'),
                    'sw-search-bar': true,
                    'sw-notification-center': true,
                    'sw-help-center': true,
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                    'sw-ignore-class': true,
                    'sw-loader': true,
                    'sw-app-actions': true,
                    'sw-extension-component-section': true,
                    'sw-skeleton': true,
                    'sw-error-summary': true,
                    'sw-app-topbar-button': true,
                    'sw-help-center-v2': true,
                    'router-link': true,
                    'sw-sales-channel-switch': true,
                    'sw-alert': true,
                    'sw-form-field-renderer': true,
                    'sw-inherit-wrapper': true,
                    'sw-ai-copilot-badge': true,
                    'sw-context-button': true,
                },
                mocks: {
                    $route: {
                        meta: {},
                        params: {
                            id: '',
                        },
                    },
                },
                provide: {
                    systemConfigApiService: {
                        getConfig: () =>
                            Promise.resolve({
                                'core.seo.redirectToCanonicalUrl': true,
                            }),
                    },
                },
            },
        },
    );
}

describe('src/module/sw-settings-seo/page/sw-settings-seo', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the settings card', async () => {
        await wrapper.vm.$nextTick();
        expect(
            wrapper
                .find(`.${classes.root}`)
                .find(`.${classes.cardView}`)
                .find(`.${classes.systemConfig}`)
                .find(`.${classes.settingsCard}`)
                .exists(),
        ).toBeTruthy();
    });
});
