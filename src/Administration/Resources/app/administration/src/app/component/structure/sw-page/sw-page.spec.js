/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/structure/sw-page';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-page', { sync: true }), {
        global: {
            stubs: {
                'sw-search-bar': true,
                'sw-notification-center': true,
                'router-link': true,
                'sw-icon': true,
                'sw-app-actions': true,
                'sw-help-center': true,
                'sw-help-center-v2': true,
                'sw-app-topbar-button': true,
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            color: 'red',
                        },
                    },
                },
            },
        },
    });
}

describe('src/app/component/structure/sw-page', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should use the header bottom-color specified with the headerBorderColor prop', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-page__head-area').attributes('style')).toBe('border-bottom-color: red; padding-right: 0px;');

        await wrapper.setProps({ headerBorderColor: 'green' });

        expect(wrapper.get('.sw-page__head-area').attributes('style')).toBe(
            'border-bottom-color: green; padding-right: 0px;',
        );
    });
});
