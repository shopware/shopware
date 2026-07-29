/**
 * @sw-package framework
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

    it('should clamp bottom tooltip horizontally when it would overflow the viewport', async () => {
        const originalInnerWidth = window.innerWidth;
        const originalInnerHeight = window.innerHeight;

        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 500,
        });
        Object.defineProperty(window, 'innerHeight', {
            configurable: true,
            value: 500,
        });

        const offsetWidthSpy = jest.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockImplementation(function () {
            return this.classList.contains('sw-tooltip') ? 100 : 0;
        });
        const offsetHeightSpy = jest.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockImplementation(function () {
            return this.classList.contains('sw-tooltip') ? 30 : 0;
        });
        const getBoundingClientRectSpy = jest
            .spyOn(HTMLElement.prototype, 'getBoundingClientRect')
            .mockImplementation(function () {
                if (this.classList.contains('sw-tooltip')) {
                    const top = parseFloat(this.style.top);
                    const left = parseFloat(this.style.left);

                    return {
                        x: left,
                        y: top,
                        top,
                        right: left + this.offsetWidth,
                        bottom: top + this.offsetHeight,
                        left,
                        width: this.offsetWidth,
                        height: this.offsetHeight,
                        toJSON: () => {},
                    };
                }

                return {
                    x: 460,
                    y: 40,
                    top: 40,
                    right: 520,
                    bottom: 80,
                    left: 460,
                    width: 60,
                    height: 40,
                    toJSON: () => {},
                };
            });

        let wrapper;

        try {
            wrapper = await createWrapper({
                message: 'a tooltip',
                position: 'bottom',
                appearance: 'light',
            });

            await wrapper.trigger('mouseenter');
            jest.runAllTimers();

            const tooltip = document.body.getElementsByClassName('sw-tooltip').item(0);

            expect(tooltip).not.toBeNull();
            expect(tooltip.classList.contains('sw-tooltip--bottom')).toBe(true);
            expect(tooltip.style.top).toBe('90px');
            expect(tooltip.style.left).toBe('390px');
        } finally {
            wrapper?.unmount();
            offsetWidthSpy.mockRestore();
            offsetHeightSpy.mockRestore();
            getBoundingClientRectSpy.mockRestore();
            Object.defineProperty(window, 'innerWidth', {
                configurable: true,
                value: originalInnerWidth,
            });
            Object.defineProperty(window, 'innerHeight', {
                configurable: true,
                value: originalInnerHeight,
            });
        }
    });
});
