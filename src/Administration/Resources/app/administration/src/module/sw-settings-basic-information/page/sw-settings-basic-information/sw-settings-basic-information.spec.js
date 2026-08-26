import { mount } from '@vue/test-utils';

/**
 * @sw-package fundamentals@framework
 */
const savedDomains = [];

const systemConfigStub = {
    props: {
        domain: { type: String, required: true },
        salesChannelSwitchable: { type: Boolean, default: false },
    },
    template: `
        <div
            class="sw-system-config"
            :data-domain="domain"
            :data-switchable="String(salesChannelSwitchable)"
        ></div>`,
    methods: {
        saveAll() {
            savedDomains.push(this.domain);

            return Promise.resolve({});
        },
    },
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-basic-information', {
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
                        getConfig: () => Promise.resolve({}),
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
                    'sw-system-config': systemConfigStub,
                    'sw-button-process': true,
                    'sw-search-bar': true,
                    'sw-notification-center': true,
                    'sw-skeleton': true,
                },
            },
        },
    );
}

describe('module/sw-settings-basic-information/page/sw-settings-basic-information', () => {
    let wrapper;

    beforeEach(async () => {
        savedDomains.length = 0;
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should render all configuration domains', async () => {
        const domains = wrapper.findAll('.sw-card-view .sw-system-config').map((config) => config.attributes('data-domain'));

        expect(domains).toEqual([
            'core.basicInformation',
            'core.cookieConsent',
            'core.cookieConsentRetention',
        ]);
    });

    it('should offer a sales channel switch for everything except the retention', async () => {
        // cookie_consent_log.cleanup deletes across the whole table, a per sales channel retention would be ignored
        const switchable = wrapper
            .findAll('.sw-card-view .sw-system-config')
            .map((config) => config.attributes('data-switchable'));

        expect(switchable).toEqual([
            'true',
            'true',
            'false',
        ]);
    });

    it('should save all configuration domains', async () => {
        await wrapper.vm.onSave();
        await flushPromises();

        expect(savedDomains).toEqual([
            'core.basicInformation',
            'core.cookieConsent',
            'core.cookieConsentRetention',
        ]);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
    });

    it('should report an error when saving a domain fails', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        jest.spyOn(wrapper.vm.$refs.systemConfigCookieConsent, 'saveAll').mockRejectedValue('save-failed');

        await wrapper.vm.onSave();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({ message: 'save-failed' });
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should stay loading while any domain is loading', async () => {
        expect(wrapper.vm.systemConfigLoading).toBe(false);

        wrapper.vm.onCookieConsentLoadingChanged(true);
        expect(wrapper.vm.systemConfigLoading).toBe(true);

        wrapper.vm.onCookieConsentLoadingChanged(false);
        wrapper.vm.onBasicInformationLoadingChanged(true);
        expect(wrapper.vm.systemConfigLoading).toBe(true);

        wrapper.vm.onBasicInformationLoadingChanged(false);
        wrapper.vm.onCookieConsentRetentionLoadingChanged(true);
        expect(wrapper.vm.systemConfigLoading).toBe(true);

        wrapper.vm.onCookieConsentRetentionLoadingChanged(false);
        expect(wrapper.vm.systemConfigLoading).toBe(false);
    });
});
