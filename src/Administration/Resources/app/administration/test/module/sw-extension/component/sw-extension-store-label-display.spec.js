import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extensions-store-label-display';


function createWrapper(labels) {
    return shallowMount(Shopware.Component.build('sw-extensions-store-label-display'), {
        propsData: {
            labels
        }
    });
}

describe('sw-extensions-store-label-display', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper([{
            label: 'Gold zerifiziert',
            color: '#cbac44'
        }]);

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the labels with the correct background', async () => {
        const wrapper = createWrapper([{
            label: 'Label Text 0',
            color: '#cbac44'
        },
        {
            label: 'Label Text 1'
        },
        {
            label: 'Label Text 2',
            color: '#000'
        }]);

        const label = wrapper.findAll('.sw-extension-label');

        expect(label.at(0).attributes().style).toBe('background-color: rgb(203, 172, 68); color: rgb(255, 255, 255);');
        expect(label.at(0).text()).toBe('Label Text 0');

        expect(label.at(1).attributes().style).toBe('background-color: rgb(255, 255, 255); color: rgb(0, 0, 0);');
        expect(label.at(1).text()).toBe('Label Text 1');

        expect(label.at(2).attributes().style).toBe('background-color: rgb(0, 0, 0); color: rgb(255, 255, 255);');
        expect(label.at(2).text()).toBe('Label Text 2');
    });
});
