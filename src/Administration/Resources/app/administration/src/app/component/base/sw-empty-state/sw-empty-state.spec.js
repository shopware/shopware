/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const $route = {
    meta: {
        $module: {
            icon: 'regular-content',
            description: 'Foo bar',
        },
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-empty-state'), {
        global: {
            mocks: { $route },
        },
        props: {
            title: 'Oh no, nothing was found.',
        },
        slots: {
            actions: '<button class="button">Primary action</button>',
        },
    });
}

describe('components/base/sw-empty-state', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should render a title', async () => {
        expect(wrapper.find('.sw-empty-state__title').text()).toBe('Oh no, nothing was found.');
    });

    it('should render the module description', async () => {
        expect(wrapper.find('.sw-empty-state__description-content').text()).toBe('Foo bar');
    });

    it('should render the subtitle instead of the module description', async () => {
        await wrapper.setProps({
            subline: 'Alternative description',
        });

        expect(wrapper.find('.sw-empty-state__description-content').text()).toBe('Alternative description');
    });

    it('should not render the description if configured', async () => {
        await wrapper.setProps({
            showDescription: false,
        });

        expect(wrapper.find('.sw-empty-state__description-content').exists()).toBeFalsy();
    });

    it('should be absolute by default', async () => {
        expect(wrapper.classes()).toContain('sw-empty-state--absolute');
    });

    it('should be render a button element when using the actions slot', async () => {
        expect(wrapper.find('.button').text()).toBe('Primary action');
    });
});

describe('components/base/sw-empty-state deprecation guard', () => {
    it('should report the deprecation while the 6.9 major is inactive', async () => {
        // Spy on the guard instead of `console.warn`, which reports each usage site only once per test file
        const deprecationSpy = jest.spyOn(Shopware.Feature, 'triggerDeprecationOrThrow');

        await createWrapper();

        // `wrapTestComponent` appends `__wrapped` to the component name
        expect(deprecationSpy).toHaveBeenCalledWith(
            'V6_9_0_0',
            expect.stringContaining(
                'The component "sw-empty-state__wrapped" is deprecated and will be removed in Shopware v6.9.0.0.\n' +
                    'Use "mt-empty-state" instead.',
            ),
        );

        deprecationSpy.mockRestore();
    });

    it.activeFeatureFlags(['v6.9.0.0'])('should throw once the 6.9 major is active', async () => {
        await expect(createWrapper()).rejects.toThrow(
            'Tried to access deprecated functionality: The component "sw-empty-state__wrapped" is deprecated',
        );
    });
});
