import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-card';

function createWrapper(propsData = {}, listeners = {}) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-card'), {
        localVue,
        stubs: {
            'sw-loader': true
        },
        provide: {
        },
        mocks: {
            $tc: v => v
        },
        listeners,
        propsData: propsData
    });
}

describe('src/app/component/base/sw-card ', () => {
    it('should display title', async () => {
        const wrapper = createWrapper({ title: 'test title' });

        expect(wrapper.find('.sw-card__title').exists()).toBeTruthy();
    });

    it('should display subtitle', async () => {
        const wrapper = createWrapper({ subtitle: 'test subtitle' });

        expect(wrapper.find('.sw-card__subtitle').exists()).toBeTruthy();
    });

    it('should display loader', async () => {
        const wrapper = createWrapper({ isLoading: true });

        console.log(wrapper.html());
        expect(wrapper.find('sw-loader-stub').exists()).toBeTruthy();
    });
});
