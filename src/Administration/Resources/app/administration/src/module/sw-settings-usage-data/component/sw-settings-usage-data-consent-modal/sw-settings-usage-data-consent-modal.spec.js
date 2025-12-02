import { mount } from '@vue/test-utils';
import { MtSwitch } from '@shopware-ag/meteor-component-library';
import swSettingsUsageDataConsentModal from './index';

function createConsentModal(storeDataConsend, userDataConsent) {
    return mount(swSettingsUsageDataConsentModal, {
        props: {
            initialStoreDataConsent: { value: storeDataConsend },
            initialUserDataConsent: { value: userDataConsent },
        },
        global: {
            stubs: {
                Teleport: { template: '<div><slot /></div>' },
            },
        },
    });
}

describe('/module/sw-settings-usage-data/component/sw-settings-usage-data-consent-modal', () => {
    describe('save preferences', () => {
        it('shows share all/share nothing button when no consent is given', async () => {
            const wrapper = await createConsentModal(false, false);

            const buttons = wrapper.findAll('.mt-modal__footer button');

            expect(buttons).toHaveLength(2);
            expect(buttons[0].text()).toBe('sw-settings-usage-data.consent-modal.actions.share-nothing');
            expect(buttons[1].text()).toBe('sw-settings-usage-data.consent-modal.actions.share-all-data');
        });

        it('shows save preferences when store data consent was given before', async () => {
            const wrapper = await createConsentModal(true, false);

            const buttons = wrapper.findAll('.mt-modal__footer button');

            expect(buttons).toHaveLength(1);
            expect(buttons[0].text()).toBe('sw-settings-usage-data.consent-modal.actions.save-preferences');
        });

        it('shows save preferences when one or both consent states changes', async () => {
            const wrapper = await createConsentModal(false, false);

            const [
                shareStoreDataSwitch,
                shareUserDataSwitch,
            ] = wrapper.findAllComponents(MtSwitch);

            await shareStoreDataSwitch.get('input').trigger('change');

            let savePreferencesButton = wrapper.find('.mt-modal__footer button');

            expect(savePreferencesButton.text()).toBe('sw-settings-usage-data.consent-modal.actions.save-preferences');

            await shareStoreDataSwitch.get('input').trigger('change');
            await shareUserDataSwitch.get('input').trigger('change');

            savePreferencesButton = wrapper.find('.mt-modal__footer button');

            expect(savePreferencesButton.text()).toBe('sw-settings-usage-data.consent-modal.actions.save-preferences');

            await shareStoreDataSwitch.get('input').trigger('change');

            savePreferencesButton = wrapper.find('.mt-modal__footer button');

            expect(savePreferencesButton.text()).toBe('sw-settings-usage-data.consent-modal.actions.save-preferences');
        });
    });
});
