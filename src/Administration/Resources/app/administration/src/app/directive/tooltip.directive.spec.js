/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';

import { tooltipRegistry } from 'src/app/directive/tooltip.directive';

jest.useFakeTimers();

const createWrapper = async (
    message,
    { components = {} } = {
        components: {},
    },
) => {
    const div = document.createElement('div');
    div.id = 'root';
    document.body.appendChild(div);

    const tooltipComponent = {
        name: 'tooltip-component',
        template: '<div v-tooltip="tooltip">hover me</div>',
        data() {
            return {
                message: message,
            };
        },
        computed: {
            tooltip() {
                return this.message;
            },
        },
        methods: {
            updateMessage(updatedMessage) {
                this.message = updatedMessage;
            },
        },
    };

    return shallowMount(tooltipComponent, {
        attachTo: '#root',
        global: {
            components,
            directives: {
                tooltip: Shopware.Directive.getByName('tooltip'),
            },
        },
    });
};

describe('directives/tooltip', () => {
    it('should show and hide tooltip', async () => {
        const wrapper = await createWrapper('a tooltip');

        await wrapper.trigger('mouseenter');
        jest.runAllTimers();

        let tooltips = document.body.getElementsByClassName('sw-tooltip');
        // Tooltip gets rendered
        expect(tooltips).toHaveLength(1);

        await wrapper.trigger('mouseleave');
        jest.runAllTimers();

        tooltips = document.body.getElementsByClassName('sw-tooltip');
        // Tooltip gets rendered
        expect(tooltips).toHaveLength(0);
    });

    it('should remove the tooltip from the registry after unmount to prevent memory leak', async () => {
        const wrapper = await createWrapper('a tooltip');
        await flushPromises();

        // Tooltip is in the registry
        expect(tooltipRegistry.size).toBe(1);

        // Unmount the wrapper
        wrapper.unmount();

        // Tooltip is removed from the registry
        expect(tooltipRegistry.size).toBe(0);
    });

    it('should not be created when target element gets deleted before creation of tooltip', async () => {
        const wrapper = await createWrapper('a tooltip');
        await wrapper.vm.$nextTick();

        await wrapper.trigger('mouseenter');

        // delete wrapper
        wrapper.unmount();

        jest.runAllTimers();

        const tooltips = document.body.getElementsByClassName('sw-tooltip');
        expect(tooltips).toHaveLength(0);
    });

    it('should not disappear if you hover the tooltip itself', async () => {
        const wrapper = await createWrapper('a tooltip');

        await wrapper.trigger('mouseenter');

        jest.runAllTimers();

        const tooltip = document.body.getElementsByClassName('sw-tooltip').item(0);
        expect(tooltip).not.toBeNull();

        await wrapper.trigger('mouseleave');
        tooltip.dispatchEvent(new Event('mouseenter'));

        jest.runAllTimers();

        expect(document.body.getElementsByClassName('sw-tooltip').item(0)).not.toBeNull();
    });
});
