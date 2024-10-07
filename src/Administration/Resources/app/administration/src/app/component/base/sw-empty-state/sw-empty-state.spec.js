/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-empty-state', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-empty-state'), {
            global: {
                stubs: {
                    'sw-icon': true,
                },
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                icon: 'default-symbol-content',
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
                actions: '<button class="sw-button">Primary action</button>',
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
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
        expect(wrapper.find('.sw-button').text()).toBe('Primary action');
    });
});
