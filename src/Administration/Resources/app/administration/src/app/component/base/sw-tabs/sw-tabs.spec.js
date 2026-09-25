/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-tabs', { sync: true }), {
        global: {
            stubs: {
                'sw-tabs-deprecated': true,
                'mt-tabs': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-tabs', () => {
    it('should render the deprecated tabs by default', async () => {
        const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.html()).not.toContain('mt-tabs');
        expect(warnSpy.mock.calls).toEqual(
            Shopware.Feature.isActive('V6_8_0_0')
                ? [
                      [
                          'sw-tabs',
                          'The "sw-tabs" wrapper is deprecated and will be removed in v6.9.0.0. Please use "mt-tabs" instead.',
                      ],
                  ]
                : [],
        );

        warnSpy.mockRestore();
    });

    it('should render the mt-tabs with an opt-in before the v6.8.0.0 feature flag is active', async () => {
        const wrapper = await createWrapper({
            props: {
                useMeteorComponent: true,
            },
        });

        expect(wrapper.html()).toContain('mt-tabs');
        expect(wrapper.html()).not.toContain('sw-tabs-deprecated');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render the mt-tabs with an opt-in', async () => {
        const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();
        const wrapper = await createWrapper({
            props: {
                useMeteorComponent: true,
            },
        });

        expect(wrapper.html()).toContain('mt-tabs');
        expect(wrapper.html()).not.toContain('sw-tabs-deprecated');
        expect(warnSpy).not.toHaveBeenCalled();

        warnSpy.mockRestore();
    });

    it('should not read the slotted items on the deprecated branch', async () => {
        // The default slot must not be invoked from `mounted`, which Vue reports as a console.warn and
        // the test setup escalates to a failure.
        const wrapper = await createWrapper({
            global: {
                stubs: {
                    'sw-tabs-deprecated': true,
                    'mt-tabs': true,
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        props: ['name'],
                        template: '<div class="sw-tabs-item"><slot /></div>',
                    },
                },
            },
            slots: {
                default: '<sw-tabs-item name="en-GB">Label en-GB</sw-tabs-item>',
            },
        });

        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.vm.activeItem).toBeNull();
    });

    it.each([
        [
            'plain text',
            'Label {{ locale }}',
            'Label en-GB',
            'Label de-DE',
        ],
        [
            'an element',
            '<span>Label {{ locale }}</span>',
            'en-GB',
            'de-DE',
        ],
        [
            'nothing',
            '',
            'en-GB',
            'de-DE',
        ],
    ])('should resolve the labels of v-for tab items whose slot holds %s', async (_, slotContent, first, second) => {
        const wrapper = await createWrapper({
            props: {
                useMeteorComponent: true,
            },
            global: {
                stubs: {
                    'sw-tabs-deprecated': true,
                    'mt-tabs': true,
                    // Keep the real component name so the fragment branch recognizes the items.
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        props: [
                            'name',
                            'title',
                            'route',
                        ],
                        template: '<div class="sw-tabs-item"><slot /></div>',
                    },
                },
            },
            slots: {
                default: `
                    <sw-tabs-item
                        v-for="locale in ['en-GB', 'de-DE']"
                        :key="locale"
                        :name="locale"
                    >${slotContent}</sw-tabs-item>
                `,
            },
        });

        // Only plain slot text yields a label of its own; anything else falls back to the name, so the
        // tab never renders unlabeled.
        expect(wrapper.vm.itemsBackwardCompatible).toEqual([
            expect.objectContaining({ name: 'en-GB', label: first }),
            expect.objectContaining({ name: 'de-DE', label: second }),
        ]);
    });
});
