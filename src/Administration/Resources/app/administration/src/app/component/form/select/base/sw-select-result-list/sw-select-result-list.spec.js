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

    it('renders the result list inside the floating content so it follows the reference on scroll', async () => {
        const content = swSelectResultList.find('.mt-floating-ui__content .sw-select-result-list__content');

        expect(content.exists()).toBe(true);
        expect(swSelectResultList.find('.sw-popover-deprecated').exists()).toBe(false);
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

    it.activeFeatureFlags(['v6.8.0.0'])(
        'forwards popoverResizeWidth without using the deprecated resizeWidth prop',
        async () => {
            const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();

            await swSelectResultList.setProps({ popoverResizeWidth: true });

            const floatingUi = swSelectResultList.getComponent({ name: 'mt-floating-ui' });

            expect(floatingUi.props('matchReferenceWidth')).toBe(true);
            expect(floatingUi.vm.$attrs).not.toHaveProperty('resize-width');
            expect(warnSpy).not.toHaveBeenCalledWith(
                'sw-popover',
                'The "resizeWidth" prop is deprecated and will be removed in v6.8.0. Please use "match-reference-width" instead.',
            );

            warnSpy.mockRestore();
        },
    );
});
