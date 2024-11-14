/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('mt-tabs', { sync: true }), {
        props: {
            items: [],
            positionIdentifier: 'jest-test-component',
        },
    });
}

describe('src/app/component/meteor-wrapper/mt-tabs', () => {
    beforeEach(() => {
        // reset store
        Shopware.State.get('tabs').tabItems = {};
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should pass the items from the props to the final component', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
                { label: 'Tab 2', name: 'tab2' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('items')).toEqual([
            { label: 'Tab 1', name: 'tab1' },
            { label: 'Tab 2', name: 'tab2' },
        ]);
    });

    it('should pass the merged items from the props and extension store to the final component', async () => {
        const wrapper = await createWrapper();

        // Set values in the extension store
        Shopware.State.get('tabs').tabItems['jest-test-component'] = [
            { label: 'Tab 3', componentSectionId: 'tab3' },
            { label: 'Tab 4', componentSectionId: 'tab4' },
        ];

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
                { label: 'Tab 2', name: 'tab2' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('items')).toEqual([
            { label: 'Tab 1', name: 'tab1' },
            { label: 'Tab 2', name: 'tab2' },
            { label: 'Tab 3', name: 'tab3', onClick: expect.any(Function) },
            { label: 'Tab 4', name: 'tab4', onClick: expect.any(Function) },
        ]);
    });
});
