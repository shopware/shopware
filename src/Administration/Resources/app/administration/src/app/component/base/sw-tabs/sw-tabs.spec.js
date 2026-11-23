/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { allowDeprecation, deprecations } from 'test/_helper_/deprecations';

const wrapperDeprecation = 'The "sw-tabs" wrapper is deprecated and will be removed in v6.9.0.0';

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
    beforeEach(() => {
        allowDeprecation(wrapperDeprecation);
    });

    it('should render the deprecated tabs by default and report them as deprecated until v6.9.0.0', async () => {
        const wrapper = await createWrapper();

        expect(deprecations).toHaveTriggered(wrapperDeprecation);
        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.html()).not.toContain('mt-tabs');
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
        const wrapper = await createWrapper({
            props: {
                useMeteorComponent: true,
            },
        });

        expect(wrapper.html()).toContain('mt-tabs');
        expect(wrapper.html()).not.toContain('sw-tabs-deprecated');
        expect(deprecations).not.toHaveTriggered(wrapperDeprecation);
    });

    it('should not read the slotted items on the deprecated branch', async () => {
        // The default slot must not be invoked from `mounted`: Vue warns about it, which fails the test.
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
                        props: ['name', 'title', 'route'],
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
