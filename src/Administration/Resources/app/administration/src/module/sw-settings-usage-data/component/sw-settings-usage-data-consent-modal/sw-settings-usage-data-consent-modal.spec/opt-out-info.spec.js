import { mount } from '@vue/test-utils';
import { MtModal, MtModalAction, MtModalRoot } from '@shopware-ag/meteor-component-library';
import swSettingsUsageDataConsentModal from '../index';

function createConsentModal(storeDataConsent) {
    return mount(swSettingsUsageDataConsentModal, {
        props: {
            storedStoreDataConsent: storeDataConsent,
            storedUserDataConsent: false,
        },
        global: {
            stubs: {
                Teleport: { template: '<div><slot /></div>' },
                'mt-modal': MtModal,
                'mt-modal-action': MtModalAction,
                'mt-modal-root': MtModalRoot,
            },
        },
    });
}

describe('sw-settings-usage-data-consent-modal opt-out information', () => {
    afterEach(() => {
        jest.useRealTimers();
    });

    it.each([
        [
            [
                'system.system_config',
                'user.update_profile',
            ],
            false,
            'snippet',
            'profile',
        ],
        [
            ['system.system_config'],
            false,
            'single-option-snippet',
            'settings',
        ],
        [
            ['user.update_profile'],
            false,
            'single-option-snippet',
            'profile',
        ],
        [
            [
                'system.system_config',
                'user.update_profile',
            ],
            true,
            'single-option-snippet',
            'profile',
        ],
        [
            [],
            false,
            null,
            'settings',
        ],
    ])('matches the visible consent options', async (roles, storeDataConsent, expectedSnippet, expectedLink) => {
        global.activeAclRoles = roles;
        jest.useFakeTimers();

        const wrapper = await createConsentModal(storeDataConsent);

        const optOutInfo = wrapper.find('.sw-setting-usage-data-consent-modal__legal');
        const optOutInfoText = optOutInfo.exists() ? optOutInfo.text() : '';
        const expectedOptOutInfo =
            expectedSnippet === null ? '' : `sw-settings-usage-data.consent-modal.opt-out-info.${expectedSnippet}`;
        const expectedOptOutInfoLink = `sw-settings-usage-data.consent-modal.opt-out-info.${expectedLink}`;

        expect(optOutInfo.exists()).toBe(expectedSnippet !== null);
        expect(optOutInfoText).toContain(expectedOptOutInfo);
        expect(wrapper.vm.optOutInfoLink).toBe(expectedOptOutInfoLink);
    });
});
