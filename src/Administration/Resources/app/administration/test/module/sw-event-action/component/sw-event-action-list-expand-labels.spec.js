import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-event-action/component/sw-event-action-list-expand-labels';

const defaultProps = {
    items: [
        { id: '1', name: 'Headless' },
        { id: '2', name: 'Storefront' }
    ]
};

function createWrapper(props = defaultProps) {
    return shallowMount(Shopware.Component.build('sw-event-action-list-expand-labels'), {
        propsData: props,
        stubs: {
            'sw-label': true
        }
    });
}

describe('src/module/sw-event-action/component/sw-event-action-list-expand-labels', () => {
    it('should be instantiated', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render all labels when items are less than default limit', () => {
        const wrapper = createWrapper();
        expect(wrapper.findAll('.sw-event-action-list-expand-labels__label').length).toBe(2);
        expect(wrapper.find('.sw-event-action-list-expand-labels__increase').exists()).toBeFalsy();
    });

    it('should should hide labels and show increase action when items are more than default limit', async () => {
        const wrapper = createWrapper({
            items: [
                { id: '1', name: 'Headless' },
                { id: '2', name: 'Storefront' },
                { id: '3', name: 'Amazon' },
                { id: '4', name: 'eBay' },
                { id: '5', name: 'Instagram' }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.sw-event-action-list-expand-labels__label').length).toBe(2);
        expect(wrapper.find('.sw-event-action-list-expand-labels__increase').text()).toBe('+3');
    });

    it('should should increase limit with default configuration', async () => {
        const wrapper = createWrapper({
            items: [
                { id: '1', name: 'Headless' },
                { id: '2', name: 'Storefront' },
                { id: '3', name: 'Amazon' },
                { id: '4', name: 'eBay' },
                { id: '5', name: 'Instagram' }
            ]
        });

        wrapper.vm.increaseLimit();

        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.sw-event-action-list-expand-labels__label').length).toBe(5);
        expect(wrapper.find('.sw-event-action-list-expand-labels__increase').exists()).toBeFalsy();
    });

    it('should increase the default limit via prop', () => {
        const wrapper = createWrapper({
            items: [
                { id: '1', name: 'Headless' },
                { id: '2', name: 'Storefront' },
                { id: '3', name: 'Amazon' },
                { id: '4', name: 'eBay' },
                { id: '5', name: 'Instagram' }
            ],
            defaultLimit: 4
        });

        expect(wrapper.findAll('.sw-event-action-list-expand-labels__label').length).toBe(4);
        expect(wrapper.find('.sw-event-action-list-expand-labels__increase').text()).toBe('+1');
    });

    it('should increase by a different value when using prop', async () => {
        const wrapper = createWrapper({
            items: [
                { id: '1', name: 'Headless' },
                { id: '2', name: 'Storefront' },
                { id: '3', name: 'Amazon' },
                { id: '4', name: 'eBay' },
                { id: '5', name: 'Instagram' },
                { id: '6', name: 'Pinterest' },
                { id: '7', name: 'Facebook' },
                { id: '8', name: 'Google Shopping' }
            ],
            defaultLimit: 4,
            increaseBy: 4
        });

        expect(wrapper.findAll('.sw-event-action-list-expand-labels__label').length).toBe(4);
        expect(wrapper.find('.sw-event-action-list-expand-labels__increase').text()).toBe('+4');

        wrapper.vm.increaseLimit();
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.sw-event-action-list-expand-labels__label').length).toBe(8);
        expect(wrapper.find('.sw-event-action-list-expand-labels__increase').exists()).toBeFalsy();
    });

    it('should render translated labels', () => {
        const wrapper = createWrapper({
            items: [
                { id: '1', name: 'Headless', translated: { name: 'Kopflos' } },
                { id: '2', name: 'Storefront', translated: { name: 'Geschäftsvorderseite' } },
                { id: '3', name: 'Amazon' },
                { id: '4', name: 'eBay', translated: { name: 'Elektrobucht' } },
                { id: '5', name: 'Instagram' }
            ],
            defaultLimit: 5
        });

        const labels = wrapper.findAll('.sw-event-action-list-expand-labels__label');

        expect(labels.at(0).text()).toBe('Kopflos');
        expect(labels.at(1).text()).toBe('Geschäftsvorderseite');
        expect(labels.at(2).text()).toBe('Amazon');
        expect(labels.at(3).text()).toBe('Elektrobucht');
        expect(labels.at(4).text()).toBe('Instagram');
    });
});
