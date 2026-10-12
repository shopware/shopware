/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-empty-state'), {
        global: {
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'regular-content',
                            description: 'Foo bar',
                        },
                    },
                },
            },
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

describe('components/base/sw-empty-state deprecation', () => {
    let warnSpy;

    beforeEach(() => {
        warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();
    });

    afterEach(() => {
        warnSpy.mockRestore();
    });

    it.deprecated('v6.8.0.0')('should not warn about the deprecation before v6.8', async () => {
        await createWrapper();

        expect(warnSpy).not.toHaveBeenCalled();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should warn about the removal in v6.9 when v6.8 is active', async () => {
        const wrapper = await createWrapper();

        expect(warnSpy).toHaveBeenCalledWith(
            'sw-empty-state',
            'The "sw-empty-state" component is deprecated and will be removed in v6.9.0.0. Please use "mt-empty-state" instead.',
        );
        expect(wrapper.find('.sw-empty-state__title').text()).toBe('Oh no, nothing was found.');
    });
});
