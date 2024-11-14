/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

const createWrapper = async () => {
    const baseComponent = {
        template: `
            <div>
                <sw-switch-field-deprecated v-model:value="checkOne" label="CheckOne" bordered name="sw-field--checkOne"></sw-switch-field-deprecated>
                <sw-switch-field-deprecated v-model:value="checkTwo" label="CheckTwo" padded name="sw-field--checkTwo"></sw-switch-field-deprecated>
                <sw-switch-field-deprecated v-model:value="checkThree" label="CheckThree" bordered padded name="sw-field--checkThree"></sw-switch-field-deprecated>
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

    return mount(baseComponent, {
        global: {
            stubs: {
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': {
                    template: '<div></div>',
                },
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
        attachTo: document.body,
    });
};

describe('app/component/form/sw-switch-field-deprecated', () => {
    it('should render three switch fields', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const switchFields = wrapper.findAll('.sw-field--switch');

        expect(switchFields).toHaveLength(3);
    });

    it('should render all labels', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const switchFieldLabels = wrapper.findAll('.sw-field__label label');

        expect(switchFieldLabels).toHaveLength(3);

        expect(switchFieldLabels.at(0).text()).toContain('CheckOne');
        expect(switchFieldLabels.at(1).text()).toContain('CheckTwo');
        expect(switchFieldLabels.at(2).text()).toContain('CheckThree');
    });

    it('should always have a label referring to a corresponding input field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

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

    [
        'checkOne',
        'checkTwo',
        'checkThree',
    ].forEach((checkboxId, index) => {
        it(`should update the value of the "${checkboxId} field when clicking its label"`, async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm[checkboxId]).toBeFalsy();
            await wrapper.findAll('.sw-field__label label').at(index).trigger('click');
            expect(wrapper.vm[checkboxId]).toBeTruthy();
        });

        it(`should update the value of the "${checkboxId} field when clicking on the input"`, async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm[checkboxId]).toBeFalsy();
            await wrapper.find(`input[name="sw-field--${checkboxId}"]`).setChecked();
            expect(wrapper.vm[checkboxId]).toBeTruthy();
        });
    });

    it('should show the label from the property', async () => {
        const wrapper = mount(
            await wrapTestComponent('sw-switch-field-deprecated', {
                sync: true,
            }),
            {
                props: {
                    label: 'Label from prop',
                },
                global: {
                    stubs: {
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-field-error': {
                            template: '<div></div>',
                        },
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                        'sw-help-text': true,
                    },
                },
            },
        );

        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = mount(
            await wrapTestComponent('sw-switch-field-deprecated', {
                sync: true,
            }),
            {
                props: {
                    label: 'Label from prop',
                },
                global: {
                    stubs: {
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-field-error': {
                            template: '<div></div>',
                        },
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                        'sw-help-text': true,
                    },
                },
                slots: {
                    label: '<template>Label from slot</template>',
                },
            },
        );
        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });

    it('should always have the corresponding css class, when its styling property has been set', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const checkboxContentWrappers = wrapper.findAll('.sw-field--switch');

        expect(checkboxContentWrappers.at(0).classes()).toContain('sw-field--switch-bordered');
        expect(checkboxContentWrappers.at(0).classes()).not.toContain('sw-field--switch-padded');
        expect(checkboxContentWrappers.at(1).classes()).not.toContain('sw-field--switch-bordered');
        expect(checkboxContentWrappers.at(1).classes()).toContain('sw-field--switch-padded');
        expect(checkboxContentWrappers.at(2).classes()).toContain('sw-field--switch-bordered');
        expect(checkboxContentWrappers.at(2).classes()).toContain('sw-field--switch-padded');
    });
});
