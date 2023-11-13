/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils_v3';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-country-state-detail', {
        sync: true,
    }), {
        props: {
            countryState: {
                isNew: () => false,
            },
        },

        global: {
            renderStubDefaultSlot: true,
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
            },

            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-container': true,
                'sw-number-field': true,
                'sw-text-field': true,
                'sw-button': true,
                'sw-empty-state': true,
            },
        },
    });
}

describe('module/sw-settings-country/component/sw-country-state-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create a new country state', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-country-state-detail__save-button');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new country state', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-country-state-detail__save-button');

        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a country state', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();
        await flushPromises();

        const saveButton = wrapper.find(
            '.sw-country-state-detail__save-button',
        );
        const countryStateNameField = wrapper.find(
            'sw-text-field-stub[label="sw-country-state-detail.labelName"]',
        );
        const countryStateShortCodeField = wrapper.find(
            'sw-text-field-stub[label="sw-country-state-detail.labelShortCode"]',
        );
        const countryStatePositionField = wrapper.find(
            'sw-number-field-stub[label="sw-country-state-detail.labelPosition"]',
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(countryStateNameField.attributes().disabled).toBeUndefined();
        expect(countryStateShortCodeField.attributes().disabled).toBeUndefined();
        expect(countryStatePositionField.attributes().disabled).toBeUndefined();
    });

    it('should not be able to edit a country state', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-country-state-detail__save-button',
        );
        const countryStateNameField = wrapper.find(
            'sw-text-field-stub[label="sw-country-state-detail.labelName"]',
        );
        const countryStateShortCodeField = wrapper.find(
            'sw-text-field-stub[label="sw-country-state-detail.labelShortCode"]',
        );
        const countryStatePositionField = wrapper.find(
            'sw-number-field-stub[label="sw-country-state-detail.labelPosition"]',
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(countryStateNameField.attributes().disabled).toBeTruthy();
        expect(countryStateShortCodeField.attributes().disabled).toBeTruthy();
        expect(countryStatePositionField.attributes().disabled).toBeTruthy();
    });
});
