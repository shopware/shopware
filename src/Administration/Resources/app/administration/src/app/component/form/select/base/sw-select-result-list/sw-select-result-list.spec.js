/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('components/sw-select-result-list', () => {
    let swSelectResultList;

    beforeEach(async () => {
        swSelectResultList = mount(await wrapTestComponent('sw-select-result-list', { sync: true }), {
            global: {
                stubs: {
                    'sw-popover': await wrapTestComponent('sw-popover', { sync: true }),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                },
            },
        });
        await flushPromises();
    });

    it('emits the paginate event when the element is scrolled to the bottom completely', async () => {
        const scrollEvent = {
            target: {
                scrollHeight: 1000,
                clientHeight: 200,
                scrollTop: 800,
            },
        };

        swSelectResultList.vm.onScroll(scrollEvent);

        expect(swSelectResultList.emitted('paginate')).toHaveLength(1);
    });

    it('emits the paginate event when the element is scrolled to the bottom with less than one pixel remaining', async () => {
        const scrollEvent = {
            target: {
                scrollHeight: 1000,
                clientHeight: 200,
                scrollTop: 799.1,
            },
        };

        swSelectResultList.vm.onScroll(scrollEvent);

        expect(swSelectResultList.emitted('paginate')).toHaveLength(1);
    });

    it('does not emit the paginate event when the element is not scrolled to the bottom', async () => {
        const scrollEvent = {
            target: {
                scrollHeight: 1000,
                clientHeight: 200,
                scrollTop: 799,
            },
        };

        swSelectResultList.vm.onScroll(scrollEvent);
        expect(swSelectResultList.emitted('paginate')).toBeUndefined();
    });
});
