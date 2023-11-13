/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/sw-switch-field';

const createWrapper = async () => {
    const baseComponent = {
        template: `
            <div>
                <sw-switch-field v-model="checkOne" label="CheckOne" bordered></sw-switch-field>
                <sw-switch-field v-model="checkTwo" label="CheckTwo" padded></sw-switch-field>
                <sw-switch-field v-model="checkThree" label="CheckThree" bordered padded></sw-switch-field>
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
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': {
                template: '<div></div>',
            },
        },
        attachTo: document.body,
    });
};

describe('app/component/form/sw-switch-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render three switch fields', async () => {
        const wrapper = await createWrapper();

        const switchFields = wrapper.findAll('.sw-field--switch');

        expect(switchFields).toHaveLength(3);
    });

    it('should render all labels', async () => {
        const wrapper = await createWrapper();

        const switchFieldLabels = wrapper.findAll('.sw-field__label label');

        expect(switchFieldLabels).toHaveLength(3);

        expect(switchFieldLabels.at(0).text()).toContain('CheckOne');
        expect(switchFieldLabels.at(1).text()).toContain('CheckTwo');
        expect(switchFieldLabels.at(2).text()).toContain('CheckThree');
    });

    it('should always have a label refering to a corresponding input field', async () => {
        const wrapper = await createWrapper();

        const switchFieldLabels = wrapper.findAll('.sw-field__label label');

        const firstSwitchInputId = wrapper.find('input[name="sw-field--checkOne"]').attributes('id');
        const firstLabelFor = switchFieldLabels.at(0).attributes('for');
        expect(firstSwitchInputId).toMatch(firstLabelFor);

        const secondSwitchInputId = wrapper.find('input[name="sw-field--checkTwo"]').attributes('id');
        const secondLabelFor = switchFieldLabels.at(1).attributes('for');
        expect(secondSwitchInputId).toMatch(secondLabelFor);

        const thirdSwitchInputId = wrapper.find('input[name="sw-field--checkThree"]').attributes('id');
        const thirdLabelFor = switchFieldLabels.at(2).attributes('for');
        expect(thirdSwitchInputId).toMatch(thirdLabelFor);
    });

    ['checkOne', 'checkTwo', 'checkThree'].forEach((checkboxId, index) => {
        it(`should click on the label of switch field "${checkboxId}" and the corresponding data updates`, async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm[checkboxId]).toBeFalsy();
            await wrapper.findAll('.sw-field__label label').at(index).trigger('click');
            expect(wrapper.vm[checkboxId]).toBeTruthy();
        });


        it(`should click on the input of switch field "${checkboxId}" and the corresponding data updates`, async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm[checkboxId]).toBeFalsy();
            await wrapper.find(`input[name="sw-field--${checkboxId}"]`).setChecked();
            expect(wrapper.vm[checkboxId]).toBeTruthy();
        });
    });

    it('should show the label from the property', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-switch-field'), {
            propsData: {
                label: 'Label from prop',
            },
            stubs: {
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': {
                    template: '<div></div>',
                },
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-switch-field'), {
            propsData: {
                label: 'Label from prop',
            },
            stubs: {
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
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
        const checkboxContentWrappers = wrapper.findAll('.sw-field--switch');

        expect(checkboxContentWrappers.at(0).classes()).toContain('sw-field--switch-bordered');
        expect(checkboxContentWrappers.at(0).classes()).not.toContain('sw-field--switch-padded');
        expect(checkboxContentWrappers.at(1).classes()).not.toContain('sw-field--switch-bordered');
        expect(checkboxContentWrappers.at(1).classes()).toContain('sw-field--switch-padded');
        expect(checkboxContentWrappers.at(2).classes()).toContain('sw-field--switch-bordered');
        expect(checkboxContentWrappers.at(2).classes()).toContain('sw-field--switch-padded');
    });
});
