import { mount } from '@vue/test-utils';
import { MtSwitch } from '@shopware-ag/meteor-component-library';
import SwSettingsUsageDataUserDataConsentCard from './index';

describe('module/sw-settings-usage-data/component/sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-user-data-consent-card', () => {
    it('emits a model update', async () => {
        const wrapper = await mount(SwSettingsUsageDataUserDataConsentCard, {
            props: {
                consent: false,
            },
            attachTo: document.body,
        });

        const consentSwitch = wrapper.getComponent(MtSwitch);

        await consentSwitch.vm.$emit('update:modelValue', true);
        await consentSwitch.vm.$emit('update:modelValue', false);

        expect(wrapper.emitted('update:consent')).toEqual([
            [true],
            [false],
        ]);
    });
});
