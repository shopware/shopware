import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-base';

async function createWrapper(customProps = {}) {
    return shallowMount(await Shopware.Component.build('sw-condition-base'), {
        stubs: {
            'sw-condition-type-select': true,
            'sw-text-field': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-field-error': true,
        },
        provide: {
            conditionDataProviderService: {
                getPlaceholderData: () => {},
                getComponentByCondition: () => {},
            },
            availableTypes: {},
            availableGroups: [],
            childAssociationField: {},
        },
        propsData: {
            condition: {},
            ...customProps,
        },
    });
}

describe('src/app/component/rule/sw-condition-base', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled condition type select', async () => {
        const wrapper = await createWrapper();

        const conditionTypeSelect = wrapper.find('sw-condition-type-select-stub');

        expect(conditionTypeSelect.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition type select', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const conditionTypeSelect = wrapper.find('sw-condition-type-select-stub');

        expect(conditionTypeSelect.attributes().disabled).toBe('true');
    });

    it('should have enabled context button', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('sw-context-button-stub');

        expect(contextButton.attributes().disabled).toBeUndefined();
    });

    it('should have disabled context button', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const contextButton = wrapper.find('sw-context-button-stub');

        expect(contextButton.attributes().disabled).toBe('true');
    });

    it('should have enabled context menu item', async () => {
        const wrapper = await createWrapper();

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.wrappers.forEach(contextMenuItem => {
            expect(contextMenuItem.attributes().disabled).toBeUndefined();
        });
    });

    it('should have disabled context menu item', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');
        contextMenuItems.wrappers.forEach(contextMenuItem => {
            expect(contextMenuItem.attributes().disabled).toBe('true');
        });
    });
});
