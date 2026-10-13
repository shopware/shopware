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

async function createParentWrapper(parentName) {
    return mount(
        {
            name: parentName,
            template: '<sw-empty-state title="Oh no, nothing was found." />',
        },
        {
            global: {
                stubs: { 'sw-empty-state': await wrapTestComponent('sw-empty-state') },
                mocks: { $route },
            },
        },
    );
}

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
    let warnSpy;

    beforeEach(() => {
        warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        warnSpy.mockRestore();
    });

    it('should warn once per using component while the 6.9 major is inactive', async () => {
        await createParentWrapper('sw-empty-state-test-parent');
        await createParentWrapper('sw-empty-state-test-parent');

        expect(warnSpy).toHaveBeenCalledTimes(1);
        expect(warnSpy).toHaveBeenCalledWith(
            '[Deprecation]',
            'The "sw-empty-state" component is deprecated and will be removed in v6.9.0. ' +
                'Replace "sw-empty-state" with "mt-empty-state" in component "sw-empty-state-test-parent".',
        );
    });

    it.activeFeatureFlags(['v6.9.0.0'])('should throw once the 6.9 major is active', async () => {
        await expect(createParentWrapper('sw-empty-state-test-major-parent')).rejects.toThrow(
            'Tried to access deprecated functionality: The "sw-empty-state" component is deprecated',
        );
    });
});
