import { shallowMount, config } from '@vue/test-utils';

import 'src/app/component/structure/sw-page';

const testColor = 'red';

function createWrapper() {
    config.mocks.$route = {
        meta: {
            $module: {
                color: testColor
            }
        }
    };

    return shallowMount(Shopware.Component.build('sw-page'), {
        stubs: {
            'sw-search-bar': true,
            'sw-notification-center': true,
            'router-link': true,
            'sw-icon': true,
            'sw-app-actions': true,
            'sw-help-center': true,
        }
    });
}


describe('src/app/component/structure/sw-page', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should use the header bottom-color specified with the headerBorderColor prop', async () => {
        const wrapper = createWrapper();

        expect(wrapper.get('.sw-page__head-area').attributes('style')).toStrictEqual('border-bottom-color: #d8dde6; padding-right: 0px;');
        await wrapper.vm.$nextTick();

        expect(wrapper.get('.sw-page__head-area').attributes('style')).toStrictEqual('border-bottom-color: red; padding-right: 0px;');

        await wrapper.setProps({ headerBorderColor: 'green' });

        expect(wrapper.get('.sw-page__head-area').attributes('style')).toStrictEqual('border-bottom-color: green; padding-right: 0px;');
    });
});
