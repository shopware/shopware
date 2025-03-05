/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/base/sw-collapse';
import 'src/app/component/sidebar/sw-sidebar-collapse';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sidebar-collapse', { sync: true }), {
        global: {
            stubs: {
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

            expect(wrapper.findComponent('.mt-icon').vm.name).toBe('regular-chevron-right-xxs');
        });
    });

    describe('all directions', () => {
        [
            'up',
            'left',
            'right',
            'down',
        ].forEach((direction) => {
            it(`has a chevron pointing ${direction}`, async () => {
                const wrapper = await createWrapper();

                await wrapper.setProps({
                    expandChevronDirection: direction,
                });

                expect(wrapper.findComponent('.mt-icon').vm.name).toBe(`regular-chevron-${direction}-xxs`);
            });
        });
    });
});
