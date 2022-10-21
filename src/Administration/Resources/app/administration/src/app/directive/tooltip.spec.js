import Vue from 'vue';
import { shallowMount, createLocalVue } from '@vue/test-utils';

import 'src/app/directive/tooltip.directive';

jest.useFakeTimers();

const createWrapper = (message) => {
    const localVue = createLocalVue();
    localVue.directive('tooltip', Shopware.Directive.getByName('tooltip'));

    const div = document.createElement('div');
    div.id = 'root';
    document.body.appendChild(div);

    const tooltipComponent = {
        name: 'tooltip-component',
        template: '<div v-tooltip="tooltip">hover me</div>',
        data() {
            return {
                message: message
            };
        },
        computed: {
            tooltip() {
                return this.message;
            }
        },
        methods: {
            updateMessage(updatedMessage) {
                this.message = updatedMessage;
            }
        }
    };

    return shallowMount(tooltipComponent, {
        localVue,
        attachTo: '#root'
    });
};

describe('directives/tooltip', () => {
    it('should show and hide tooltip', async () => {
        const wrapper = createWrapper('a tooltip');

        wrapper.trigger('mouseenter');
        jest.runAllTimers();

        let tooltips = document.body.getElementsByClassName('sw-tooltip');
        // Tooltip gets rendered
        expect(tooltips.length).toBe(1);

        wrapper.trigger('mouseleave');
        jest.runAllTimers();

        tooltips = document.body.getElementsByClassName('sw-tooltip');
        // Tooltip gets rendered
        expect(tooltips.length).toBe(0);

        wrapper.destroy();
    });

    it('should render vue components', async () => {
        // register component globally,
        // so that new vue instances created from the tooltip can access it
        Vue.component('sw-test', {
            template: '<div class="sw-test"/>'
        });

        const wrapper = createWrapper('This is a <sw-test></sw-test>');

        wrapper.trigger('mouseenter');
        jest.runAllTimers();

        const tooltips = document.body.getElementsByClassName('sw-tooltip');
        // Tooltip gets rendered
        expect(tooltips.length).toBe(1);

        // custom element gets rendered inside tooltip
        expect(tooltips.item(0).getElementsByClassName('sw-test').length).toBe(1);

        wrapper.destroy();
    });

    it('should render vue components on template updates', async () => {
        // register component globally,
        // so that new vue instances created from the tooltip can access it
        Vue.component('sw-test', {
            template: '<div class="sw-test"/>'
        });

        const wrapper = createWrapper('a tooltip');
        wrapper.vm.updateMessage('This is a <sw-test></sw-test>');
        await wrapper.vm.$nextTick();

        wrapper.trigger('mouseenter');
        jest.runAllTimers();

        const tooltips = document.body.getElementsByClassName('sw-tooltip');
        // Tooltip gets rendered
        expect(tooltips.length).toBe(1);

        // custom element gets rendered inside tooltip
        expect(tooltips.item(0).getElementsByClassName('sw-test').length).toBe(1);

        wrapper.destroy();
    });

    it('should not be created when target element gets deleted before creation of tooltip', async () => {
        const wrapper = createWrapper('a tooltip');
        await wrapper.vm.$nextTick();

        wrapper.trigger('mouseenter');

        // delete wrapper
        wrapper.destroy();

        jest.runAllTimers();

        const tooltips = document.body.getElementsByClassName('sw-tooltip');
        expect(tooltips.length).toBe(0);
    });

    it('should not disappear if you hover the tooltip itself', async () => {
        const wrapper = createWrapper('a tooltip');

        wrapper.trigger('mouseenter');

        jest.runAllTimers();

        const tooltip = document.body.getElementsByClassName('sw-tooltip').item(0);
        expect(tooltip).not.toBeNull();

        wrapper.trigger('mouseleave');
        tooltip.dispatchEvent(new Event('mouseenter'));

        jest.runAllTimers();

        expect(document.body.getElementsByClassName('sw-tooltip').item(0)).not.toBeNull();
    });
});
