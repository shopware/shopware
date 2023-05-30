/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-checkbox-field';

const createWrapper = async () => {
    const baseComponent = {
        template: `
            <div>
                <sw-checkbox-field v-model="checkOne" label="CheckOne" bordered/>
                <sw-checkbox-field v-model="checkTwo" label="CheckTwo" padded/>
                <sw-checkbox-field v-model="checkThree" label="CheckThree" bordered padded/>
            </div>
        `,

        data() {
            return {
                checkOne: false,
                checkTwo: false,
                checkThree: false,
            };
        },
    };

    return shallowMount(baseComponent, {
        stubs: {
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': true,
            'sw-field-error': {
                template: '<div></div>',
            },
        },
        attachTo: document.body,
    });
};

describe('app/component/form/sw-checkbox-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render three checkbox fields', async () => {
        const wrapper = await createWrapper();

        const checkboxFields = wrapper.findAll('.sw-field--checkbox');

        expect(checkboxFields).toHaveLength(3);
    });

    it('should render all labels', async () => {
        const wrapper = await createWrapper();

        const checkboxLabels = wrapper.findAll('.sw-field__label label');

        expect(checkboxLabels).toHaveLength(3);

        expect(checkboxLabels.at(0).text()).toContain('CheckOne');
        expect(checkboxLabels.at(1).text()).toContain('CheckTwo');
        expect(checkboxLabels.at(2).text()).toContain('CheckThree');
    });

    it('should always have a label refering to a corresponding input field', async () => {
        const wrapper = await createWrapper();
        const checkboxLabels = wrapper.findAll('.sw-field__label label');

        const firstCheckboxInputId = wrapper.find('input[name="sw-field--checkOne"]').attributes('id');
        const firstLabelFor = checkboxLabels.at(0).attributes('for');
        expect(firstCheckboxInputId).toMatch(firstLabelFor);

        const secondCheckboxInputId = wrapper.find('input[name="sw-field--checkTwo"]').attributes('id');
        const secondLabelFor = checkboxLabels.at(1).attributes('for');
        expect(secondCheckboxInputId).toMatch(secondLabelFor);

        const thirdCheckboxInputId = wrapper.find('input[name="sw-field--checkThree"]').attributes('id');
        const thirdLabelFor = checkboxLabels.at(2).attributes('for');
        expect(thirdCheckboxInputId).toMatch(thirdLabelFor);
    });

    ['checkOne', 'checkTwo', 'checkThree'].forEach((checkboxId, index) => {
        it(`should click on the label of Checkbox "${checkboxId}" and the corresponding data updates`, async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm[checkboxId]).toBeFalsy();
            await wrapper.findAll('.sw-field__label label').at(index).trigger('click');
            expect(wrapper.vm[checkboxId]).toBeTruthy();
        });


        it(`should click on the input of Checkbox "${checkboxId}" and the corresponding data updates`, async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm[checkboxId]).toBeFalsy();
            await wrapper.find(`input[name="sw-field--${checkboxId}"]`).setChecked();
            expect(wrapper.vm[checkboxId]).toBeTruthy();
        });
    });

    it('should show the label from the property', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-checkbox-field'), {
            propsData: {
                label: 'Label from prop',
            },
            stubs: {
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-icon': true,
                'sw-field-error': {
                    template: '<div></div>',
                },
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-checkbox-field'), {
            propsData: {
                label: 'Label from prop',
            },
            stubs: {
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-icon': true,
                'sw-field-error': {
                    template: '<div></div>',
                },
            },
            scopedSlots: {
                label: '<template>Label from slot</template>',
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });

    it('should always have the corresponding css class, when its styling property has been set', async () => {
        const wrapper = await createWrapper();
        const checkboxContentWrappers = wrapper.findAll('.sw-field--checkbox__content');

        expect(checkboxContentWrappers.at(0).classes()).toContain('is--bordered');
        expect(checkboxContentWrappers.at(0).classes()).not.toContain('is--padded');
        expect(checkboxContentWrappers.at(1).classes()).not.toContain('is--bordered');
        expect(checkboxContentWrappers.at(1).classes()).toContain('is--padded');
        expect(checkboxContentWrappers.at(2).classes()).toContain('is--bordered');
        expect(checkboxContentWrappers.at(2).classes()).toContain('is--padded');
    });
});
