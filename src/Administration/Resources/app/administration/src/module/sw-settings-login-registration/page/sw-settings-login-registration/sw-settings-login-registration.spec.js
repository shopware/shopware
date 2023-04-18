import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsLoginRegistration from 'src/module/sw-settings-login-registration/page/sw-settings-login-registration';
import swSystemConfig from 'src/module/sw-settings/component/sw-system-config';

Shopware.Component.register('sw-settings-login-registration', swSettingsLoginRegistration);
Shopware.Component.register('sw-system-config', swSystemConfig);

const classes = {
    root: 'sw-page__main-content',
    cardView: 'sw-card-view',
    systemConfig: 'sw-system-config',
    settingsCard: 'sw-card',
};

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-login-registration'), {
        localVue,
        mocks: {
            $route: {
                meta: {},
            },
        },
        provide: { systemConfigApiService: {
            getConfig: () => Promise.resolve({
                'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel': true,
            }),
        } },
        stubs: {
            'sw-page': {
                template: `
                     <div class="sw-page">
                          <slot name="smart-bar-actions"></slot>
                          <div class="sw-page__main-content">
                            <slot name="content"></slot>
                          </div>
                          <slot></slot>
                     </div>`,
            },
            'sw-icon': true,
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>',
            },
            'sw-card-view': {
                template: '<div class="sw-card-view"><slot></slot></div>',
            },
            'sw-button-process': true,
            'sw-system-config': await Shopware.Component.build('sw-system-config'),
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-skeleton': true,
        },
    });
}

describe('module/sw-settings-login-registration/page/sw-settings-login-registration', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the settings card system', async () => {
        await wrapper.vm.$nextTick();
        expect(
            wrapper.find(`.${classes.root}`)
                .find(`.${classes.cardView}`)
                .find(`.${classes.systemConfig}`)
                .find(`.${classes.settingsCard}`)
                .exists(),
        ).toBeTruthy();
    });
});

