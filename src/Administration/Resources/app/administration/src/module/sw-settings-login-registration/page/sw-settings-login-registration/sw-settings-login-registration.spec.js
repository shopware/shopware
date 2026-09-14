import { mount } from '@vue/test-utils';

const classes = {
    root: 'sw-page__main-content',
    cardView: 'sw-card-view',
    systemConfig: 'sw-system-config',
    settingsCard: 'mt-card',
};

/**
 * @sw-package fundamentals@framework
 */
async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-login-registration', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        meta: {},
                    },
                },
                provide: {
                    systemConfigApiService: {
                        getConfig: () =>
                            Promise.resolve({
                                'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel': true,
                            }),
                    },
                },
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
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-button-process': true,
                    'sw-system-config': await wrapTestComponent('sw-system-config'),
                    'sw-search-bar': true,
                    'sw-notification-center': true,
                    'sw-skeleton': true,
                    'sw-sales-channel-switch': true,

                    'sw-form-field-renderer': true,
                    'sw-inherit-wrapper': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        },
    );
}

describe('module/sw-settings-login-registration/page/sw-settings-login-registration', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('disables the company name switches while the account type selection is off', async () => {
        wrapper.vm.onLoginRegistrationConfigChanged({
            'core.loginRegistration.showAccountTypeSelection': false,
        });

        expect(wrapper.vm.disabledLoginRegistrationElements).toEqual([
            'core.loginRegistration.showNameFieldsForCompanyAccounts',
            'core.loginRegistration.nameFieldsRequiredForCompanyAccounts',
        ]);
    });

    it('leaves the company name switches alone while the account type selection is on', async () => {
        wrapper.vm.onLoginRegistrationConfigChanged({
            'core.loginRegistration.showAccountTypeSelection': true,
        });

        expect(wrapper.vm.disabledLoginRegistrationElements).toEqual([]);
    });

    it('should contain the settings card system', async () => {
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
