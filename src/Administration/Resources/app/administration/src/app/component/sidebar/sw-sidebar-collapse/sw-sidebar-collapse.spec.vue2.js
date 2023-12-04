/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils_v2';
import 'src/app/component/base/sw-collapse';
import 'src/app/component/sidebar/sw-sidebar-collapse';

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-sidebar-collapse'), {
        stubs: {
            'sw-icon': {
                props: [
                    'name',
                ],
                template: '<span class="sw-icon">{{ name }}</span>',
            },
            'sw-collapse': true,
        },
        mocks: {
            $tc: (snippetPath, count, values) => snippetPath + count + JSON.stringify(values),
        },
    });
}

describe('src/app/component/sidebar/sw-sidebar-collapse', () => {
    describe('no props', () => {
        it('has a chevron pointing right', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.find('.sw-sidebar-collapse__expand-button').text()).toContain('right');
        });
    });

    describe('prop expandChevronDirection down', () => {
        it('has a chevron pointing down', async () => {
            const wrapper = await createWrapper();

            await wrapper.setProps({
                expandChevronDirection: 'bottom',
            });

            expect(wrapper.find('.sw-sidebar-collapse__expand-button').text()).toContain('bottom');
        });
    });
});
