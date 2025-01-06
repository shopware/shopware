/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-checkbox-field';

const defaultData = {
    indeterminateOne: false,
    checkOne: false,
    checkTwo: false,
    checkThree: false,
};

const createWrapper = async (data = defaultData) => {
    const baseComponent = {
        template: `
            <div>
                <sw-checkbox-field v-model:value="checkOne" label="CheckOne"  bordered :partly-checked="indeterminateOne" name="sw-field--checkOne" />
                <sw-checkbox-field v-model:value="checkTwo" label="CheckTwo" aria-label="Check Two" padded name="sw-field--checkTwo" />
                <sw-checkbox-field v-model:value="checkThree" label="CheckThree" bordered padded name="sw-field--checkThree" />
            </div>
        `,

        data() {
            return {
                ...data,
            };
        },
    };

    return mount(baseComponent, {
        global: {
            stubs: {
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field-deprecated'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': true,
                'sw-field-error': {
                    template: '<div></div>',
                },
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
            },
        },
        attachTo: document.body,
    });
};

describe('app/component/form/sw-checkbox-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render three checkbox fields', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const checkboxFields = wrapper.findAll('.sw-field--checkbox');

        expect(checkboxFields).toHaveLength(3);
    });

    it('should render all labels', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const checkboxLabels = wrapper.findAll('.sw-field__label label');

        expect(checkboxLabels).toHaveLength(3);

        expect(checkboxLabels.at(0).text()).toContain('CheckOne');
        expect(checkboxLabels.at(1).text()).toContain('CheckTwo');
        expect(checkboxLabels.at(2).text()).toContain('CheckThree');
    });

    it('should always have a label referring to a corresponding input field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
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

    [
        'checkOne',
        'checkTwo',
        'checkThree',
    ].forEach((checkboxId, index) => {
        it(`should click on the label of Checkbox "${checkboxId}" and the corresponding data updates`, async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm[checkboxId]).toBeFalsy();
            await wrapper.findAll('.sw-field__label label').at(index).trigger('click');
            expect(wrapper.vm[checkboxId]).toBeTruthy();

            expect(wrapper.find('.sw-field__checkbox-state sw-icon-stub').attributes('name')).toBe('regular-checkmark-xxs');
        });

        it(`should click on the input of Checkbox "${checkboxId}" and the corresponding data updates`, async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm[checkboxId]).toBeFalsy();
            await wrapper.find(`input[name="sw-field--${checkboxId}"]`).setChecked();
            expect(wrapper.vm[checkboxId]).toBeTruthy();

            expect(wrapper.find('.sw-field__checkbox-state sw-icon-stub').attributes('name')).toBe('regular-checkmark-xxs');
        });
    });

    it('should show the label from the property', async () => {
        const wrapper = mount(
            await wrapTestComponent('sw-checkbox-field-deprecated', {
                sync: true,
            }),
            {
                props: {
                    label: 'Label from prop',
                },
                global: {
                    stubs: {
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-icon': true,
                        'sw-field-error': {
                            template: '<div></div>',
                        },
                        'sw-help-text': true,
                        'sw-ai-copilot-badge': true,
                        'sw-inheritance-switch': true,
                    },
                },
            },
        );

        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = mount(
            await wrapTestComponent('sw-checkbox-field-deprecated', {
                sync: true,
            }),
            {
                props: {
                    label: 'Label from prop',
                },
                global: {
                    stubs: {
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-icon': true,
                        'sw-field-error': {
                            template: '<div></div>',
                        },
                        'sw-help-text': true,
                        'sw-ai-copilot-badge': true,
                        'sw-inheritance-switch': true,
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

        const checkboxContentWrappers = wrapper.findAll('.sw-field--checkbox__content');

        expect(checkboxContentWrappers.at(0).classes()).toContain('is--bordered');
        expect(checkboxContentWrappers.at(0).classes()).not.toContain('is--padded');
        expect(checkboxContentWrappers.at(1).classes()).not.toContain('is--bordered');
        expect(checkboxContentWrappers.at(1).classes()).toContain('is--padded');
        expect(checkboxContentWrappers.at(2).classes()).toContain('is--bordered');
        expect(checkboxContentWrappers.at(2).classes()).toContain('is--padded');
    });

    it('should display indeterminate icon if indeterminate state is active and checkbox is not checked', async () => {
        const wrapper = await createWrapper({
            ...defaultData,
            indeterminateOne: true,
            checkOne: false,
        });
        await flushPromises();

        const firstCheckbox = wrapper.find('.sw-field--checkbox');

        expect(firstCheckbox.find('input').element.checked).toBe(false);
        expect(firstCheckbox.find('.sw-field__checkbox-state sw-icon-stub').attributes('name')).toBe('regular-minus-xxs');
    });

    it('should switch the checked icon if partlyChecked state and state is active and checkbox is partlyChecked', async () => {
        const wrapper = await createWrapper({
            ...defaultData,
            indeterminateOne: true,
            checkOne: false,
        });

        await flushPromises();

        const firstCheckbox = wrapper.find('.sw-field--checkbox');
        const icon = firstCheckbox.find('.sw-field__checkbox-state sw-icon-stub');

        expect(icon.attributes('name')).toBe('regular-minus-xxs');
        await firstCheckbox.find('input').setChecked();
        expect(icon.attributes('name')).toBe('regular-checkmark-xxs');
    });

    it('should add partlyChecked class to checkbox if partlyChecked state is active and checkbox is not checked', async () => {
        const wrapper = await createWrapper({
            ...defaultData,
            indeterminateOne: true,
            checkOne: false,
        });

        await flushPromises();

        expect(wrapper.find('.sw-field--checkbox').classes()).toContain('is--partly-checked');
    });

    it('should add the ariaLabel prop to the input element', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const firstCheckbox = wrapper.find('.sw-field--checkbox');
        expect(firstCheckbox.find('input').attributes('aria-label')).toBe('CheckOne');

        const secondCheckbox = wrapper.findAll('.sw-field--checkbox').at(1);
        expect(secondCheckbox.find('input').attributes('aria-label')).toBe('Check Two');
    });
});
