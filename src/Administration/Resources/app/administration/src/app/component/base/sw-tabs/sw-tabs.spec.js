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
    // @deprecated tag:v6.8.0 - The test will be removed with the legacy tabs implementation.
    it.deprecated('v6.8.0.0')('should render the deprecated tabs', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.html()).not.toContain('mt-tabs');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render the mt-tabs', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-tabs');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should resolve labels from slot text for v-for / fragment tab items', async () => {
        const wrapper = await createWrapper({
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
                        >Label {{ locale }}</sw-tabs-item>
                    `,
            },
        });

        // Label comes from the slot text, name from the :name prop - not name for both.
        expect(wrapper.vm.itemsBackwardCompatible).toEqual([
            expect.objectContaining({ name: 'en-GB', label: 'Label en-GB' }),
            expect.objectContaining({ name: 'de-DE', label: 'Label de-DE' }),
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should fall back to the name for fragment tab items without plain text slot content',
        async () => {
            const wrapper = await createWrapper({
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
                        ><span>Label {{ locale }}</span></sw-tabs-item>
                    `,
                },
            });

            // A wrapped slot child yields no slot text, so the name keeps the tab from rendering unlabeled.
            expect(wrapper.vm.itemsBackwardCompatible).toEqual([
                expect.objectContaining({ name: 'en-GB', label: 'en-GB' }),
                expect.objectContaining({ name: 'de-DE', label: 'de-DE' }),
            ]);
        },
    );
});
