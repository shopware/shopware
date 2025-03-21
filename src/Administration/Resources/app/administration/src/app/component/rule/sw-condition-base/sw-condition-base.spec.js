/**
 * @sw-package fundamentals@after-sales
 */

import { mount } from '@vue/test-utils';

const snippets = {
    'sw-product.settingsForm.placeholderTime': 'eg. 31...',
    'global.sw-tagged-field.text-default-placeholder': 'Press the enter key to add values.',
    'global.sw-condition.condition.zipCodeWildcardPlaceholder': 'Use "*" as a wildcard character...',
};

async function createWrapper(customProps = {}, condition = {}, conditionTypeData = {}) {
    return mount(await wrapTestComponent('sw-condition-base', { sync: true }), {
        props: {
            condition: { ...condition },
            ...customProps,
        },
        global: {
            stubs: {
                'sw-condition-type-select': true,
                'sw-text-field': true,
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated'),
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-context-menu-item': true,
                'sw-field-error': true,
            },
            provide: {
                conditionDataProviderService: {
                    getComponentByCondition: () => {},
                    getByType: () => conditionTypeData,
                },
                availableTypes: {},
                availableGroups: [],
                childAssociationField: {},
            },
            mocks: {
                $tc: (snippetKey) => snippets[snippetKey] ?? snippetKey,
            },
        },
    });
}

describe('src/app/component/rule/sw-condition-base', () => {
    it('should have enabled condition type select', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const conditionTypeSelect = wrapper.find('sw-condition-type-select-stub');

        expect(conditionTypeSelect.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition type select', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });
        await flushPromises();

        const conditionTypeSelect = wrapper.find('sw-condition-type-select-stub');

        expect(conditionTypeSelect.attributes().disabled).toBe('true');
    });

    it('should have enabled context button', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const contextButton = wrapper.find('.sw-condition__context-button');

        expect(contextButton.attributes().disabled).toBeUndefined();
    });

    it('should have disabled context button', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });
        await flushPromises();

        const contextButton = wrapper.find('.sw-condition__context-button');

        expect(contextButton.classes('is--disabled')).toBeTruthy();
    });

    it('should have enabled context menu item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.forEach((contextMenuItem) => {
            expect(contextMenuItem.attributes().disabled).toBeUndefined();
        });
    });

    it('should have disabled context menu item', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });
        await flushPromises();

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.forEach((contextMenuItem) => {
            expect(contextMenuItem.attributes().disabled).toBe('true');
        });
    });

    it('should return single placeholder', async () => {
        const placeholder = 'sw-product.settingsForm.placeholderTime';

        const wrapper = await createWrapper(
            {},
            { type: 'customerDaysSinceLastLogin' },
            { snippets: { fields: { daysPassed: { placeholder: placeholder } } } },
        );
        await flushPromises();

        expect(wrapper.vm.getPlaceholder('daysPassed')).toBe(snippets[placeholder]);
    });

    it('should return empty string on missing placeholder', async () => {
        const wrapper = await createWrapper({}, { type: 'customerDaysSinceLastLogin' }, {});
        await flushPromises();

        expect(wrapper.vm.getPlaceholder('daysPassed')).toBe('');
    });

    it('should return combined placeholder', async () => {
        const placeholder = [
            'global.sw-tagged-field.text-default-placeholder',
            ' ',
            'global.sw-condition.condition.zipCodeWildcardPlaceholder',
        ];

        const wrapper = await createWrapper(
            {},
            { type: 'customerBillingZipCode' },
            { snippets: { fields: { alphanumericZipCodes: { placeholder: placeholder } } } },
        );
        await flushPromises();

        expect(wrapper.vm.getPlaceholder('alphanumericZipCodes')).toBe(
            `${snippets[placeholder[0]]} ${snippets[placeholder[2]]}`,
        );
    });

    it('should filter undefined placeholder', async () => {
        const placeholder = [
            'global.sw-tagged-field.text-default-placeholder',
            ' ',
            undefined,
            'global.sw-condition.condition.zipCodeWildcardPlaceholder',
        ];

        const wrapper = await createWrapper(
            {},
            { type: 'customerBillingZipCode' },
            { snippets: { fields: { alphanumericZipCodes: { placeholder: placeholder } } } },
        );
        await flushPromises();

        expect(wrapper.vm.getPlaceholder('alphanumericZipCodes')).toBe(
            `${snippets[placeholder[0]]} ${snippets[placeholder[3]]}`,
        );
    });

    it('should emit create-before event', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-condition__context-button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-condition__create-before-action').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('create-before')).toBeDefined();
    });

    it('should emit create-after event', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-condition__context-button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-condition__create-after-action').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('create-after')).toBeDefined();
    });

    it('should emit condition-delete event', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-condition__context-button').trigger('click');
        await flushPromises();

        await wrapper.find('sw-context-menu-item-stub[variant="danger"]').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('condition-delete')).toBeDefined();
    });
});
