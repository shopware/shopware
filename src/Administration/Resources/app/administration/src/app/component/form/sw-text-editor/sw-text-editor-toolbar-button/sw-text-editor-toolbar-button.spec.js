/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(buttonConfig) {
    return mount(await wrapTestComponent('sw-text-editor-toolbar-button', { sync: true }), {
        props: {
            buttonConfig: {
                type: 'data-mapping',
                icon: 'regular-variables-xs',
                expanded: true,
                children: [
                    { type: 'product.name', name: 'product.name', title: 'product.name' },
                    { type: 'product.customFields.color', name: 'product.customFields.color', title: 'Colour' },
                ],
                ...buttonConfig,
            },
        },
        global: {
            stubs: {
                'sw-icon': true,
                'mt-icon': true,
                'sw-compact-colorpicker': true,
                'sw-text-editor-link-menu': true,
                'sw-text-editor-toolbar-table-button': true,
            },
        },
    });
}

describe('components/form/sw-text-editor/sw-text-editor-toolbar-button', () => {
    it('should not render a search input for children that are not searchable', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-text-editor-toolbar-button__children-search input').exists()).toBe(false);
        expect(wrapper.findAll('.sw-text-editor-toolbar-button__children-child')).toHaveLength(2);
    });

    it('should label the search input of searchable children', async () => {
        const wrapper = await createWrapper({ searchable: true });

        const searchInput = wrapper.find('.sw-text-editor-toolbar-button__children-search input');
        const label = wrapper.find(
            `.sw-text-editor-toolbar-button__children-search label[for="${searchInput.attributes('id')}"]`,
        );
        expect(label.text()).toBe('sw-text-editor-toolbar.childSearch.label');
    });

    it('should filter searchable children by name and title case-insensitively', async () => {
        const wrapper = await createWrapper({ searchable: true });
        const searchInput = wrapper.find('.sw-text-editor-toolbar-button__children-search input');

        await searchInput.setValue('PRODUCT.NAME');
        expect(wrapper.findAll('.sw-text-editor-toolbar-button__children-child').map((child) => child.text())).toEqual([
            'product.name',
        ]);

        await searchInput.setValue('colour');
        expect(wrapper.findAll('.sw-text-editor-toolbar-button__children-child').map((child) => child.text())).toEqual([
            'product.customFields.color',
        ]);
        expect(wrapper.find('.sw-text-editor-toolbar-button__children-empty').exists()).toBe(false);
    });

    it('should reset the search term when the children menu closes', async () => {
        const wrapper = await createWrapper({ searchable: true });

        await wrapper.find('.sw-text-editor-toolbar-button__children-search input').setValue('colour');
        await wrapper.setProps({ buttonConfig: { ...wrapper.props('buttonConfig'), expanded: false } });
        await wrapper.setProps({ buttonConfig: { ...wrapper.props('buttonConfig'), expanded: true } });

        expect(wrapper.find('.sw-text-editor-toolbar-button__children-search input').element.value).toBe('');
        expect(wrapper.findAll('.sw-text-editor-toolbar-button__children-child')).toHaveLength(2);
    });

    it('should pass a searchable parent along with the clicked child', async () => {
        const wrapper = await createWrapper({ searchable: true });

        await wrapper.find('.sw-text-editor-toolbar-button__children-child').trigger('click');

        const [
            child,
            parent,
        ] = wrapper.emitted('button-click')[0];
        expect(child.name).toBe('product.name');
        expect(parent).toBe(wrapper.props('buttonConfig'));
    });
});
