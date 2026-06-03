import { mount } from '@vue/test-utils';
import SwSettingsServicesGmvInfo from './index';

describe('src/module/sw-settings-services/component/sw-settings-services-gmv-info', () => {
    it('renders the GMV requirement help text', () => {
        const wrapper = mount(SwSettingsServicesGmvInfo, {
            global: {
                stubs: {
                    'mt-help-text': {
                        props: [
                            'text',
                            'width',
                        ],
                        template: '<button class="mt-help-text">{{ text }} {{ width }}</button>',
                    },
                },
            },
        });

        const helpText = wrapper.get('.mt-help-text');

        expect(helpText.text()).toContain('sw-settings-services.gmv-info.text');
        expect(helpText.text()).toContain('420');
    });
});
