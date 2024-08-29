/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/base/sw-collapse';
import 'src/app/component/sidebar/sw-sidebar-collapse';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sidebar-collapse', { sync: true }), {
        global: {
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
