/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import { MtPopover, MtButton, MtIcon, MtSwitch } from '@shopware-ag/meteor-component-library';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-extension-teaser-popover', { sync: true }), {
        props: {
            positionIdentifier: 'test-position',
            ...props,
        },
        global: {
            stubs: {
                'mt-button': MtButton,
                'mt-icon': MtIcon,
                'mt-switch': MtSwitch,
                'mt-popover': MtPopover,
                'sw-iframe-renderer': true,
            },
        },
    });
}

jest.useFakeTimers();

describe('src/app/component/extension-api/sw-extension-teaser-popover', () => {
    let wrapper = null;
    let store = null;

    beforeEach(async () => {
        store = Shopware.Store.get('teaserPopover');
        store.identifier = {};
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show button component', async () => {
        store.addPopoverComponent({
            positionId: 'test-position',
            src: 'http://localhost:8080',
            component: 'button',
            props: {
                locationId: 'locationId',
                label: 'Ask AI Copilot',
            },
        });

        wrapper = await createWrapper();
        const buttonComponent = wrapper.find('.mt-button');
        expect(buttonComponent.exists()).toBeTruthy();
        expect(buttonComponent.text()).toBe('Ask AI Copilot');
    });

    it('should show switch component', async () => {
        store.addPopoverComponent({
            positionId: 'test-position',
            src: 'http://localhost:8080',
            component: 'switch-field',
            props: {
                locationId: 'locationId',
                label: 'Preview mode',
            },
        });

        wrapper = await createWrapper();
        const switchFieldComponent = wrapper.find('.mt-field--switch');
        expect(switchFieldComponent.exists()).toBeTruthy();
    });

    it('should show custom trigger frame', async () => {
        store.addPopoverComponent({
            positionId: 'test-position',
            src: 'http://localhost:8080',
            component: 'custom',
            props: {
                locationId: 'locationId',
            },
        });

        wrapper = await createWrapper();

        const customComponent = wrapper.find('sw-iframe-renderer-stub');
        expect(customComponent.exists()).toBeTruthy();
    });

    it('should show component from props', async () => {
        wrapper = await createWrapper();

        await wrapper.setProps({
            component: {
                component: 'button',
                props: {
                    label: 'Upload flow',
                    locationId: 'locationId',
                },
            },
        });

        const buttonComponent = wrapper.find('.mt-button');
        expect(buttonComponent.exists()).toBeTruthy();
        expect(buttonComponent.text()).toBe('Upload flow');
    });


    it('should show popover content if mouse enter trigger component', async () => {
        store.addPopoverComponent({
            positionId: 'test-position',
            src: 'http://localhost:8080',
            component: 'button',
            props: {
                locationId: 'locationId',
                label: 'Ask AI Copilot',
            },
        });

        wrapper = await createWrapper();

        const triggerComponent = wrapper.find('.sw-extension-teaser-popover__trigger');
        await triggerComponent.trigger('mouseenter');

        const contentComponent = document.body.querySelector('.sw-extension-teaser-popover__content');
        expect(contentComponent).toBeTruthy();

        expect(wrapper.vm.isInsideComponent).toBeTruthy();
    });

    it('should hide popover content if mouse leave trigger component', async () => {
        store.addPopoverComponent({
            positionId: 'test-position',
            src: 'http://localhost:8080',
            component: 'button',
            props: {
                locationId: 'locationId',
                label: 'Ask AI Copilot',
            },
        });

        wrapper = await createWrapper();

        const triggerComponent = wrapper.find('.sw-extension-teaser-popover__trigger');
        await triggerComponent.trigger('mouseenter');

        expect(wrapper.vm.isInsideComponent).toBeTruthy();

        await triggerComponent.trigger('mouseleave');
        jest.runAllTimers();

        expect(wrapper.vm.isInsideComponent).toBeFalsy();
    });

    it('should show popover content if mouse enter popover component', async () => {
        store.addPopoverComponent({
            positionId: 'test-position',
            src: 'http://localhost:8080',
            component: 'button',
            props: {
                locationId: 'locationId',
                label: 'Ask AI Copilot',
            },
        });

        wrapper = await createWrapper();

        const triggerComponent = wrapper.find('.sw-extension-teaser-popover__trigger');
        await triggerComponent.trigger('mouseenter');

        const contentComponent = document.body.querySelector('.sw-extension-teaser-popover__content');

        await triggerComponent.trigger('mouseleave');
        contentComponent.dispatchEvent(new Event('mouseenter'));

        jest.runAllTimers();

        expect(wrapper.vm.isInsideComponent).toBeTruthy();
    });

    it('should hide popover content if mouse leave popover trigger and content component', async () => {
        store.addPopoverComponent({
            positionId: 'test-position',
            src: 'http://localhost:8080',
            component: 'button',
            props: {
                locationId: 'locationId',
                label: 'Ask AI Copilot',
            },
        });

        wrapper = await createWrapper();

        const triggerComponent = wrapper.find('.sw-extension-teaser-popover__trigger');
        await triggerComponent.trigger('mouseenter');

        const contentComponent = document.body.querySelector('.sw-extension-teaser-popover__content');

        await triggerComponent.trigger('mouseleave');
        contentComponent.dispatchEvent(new Event('mouseenter'));
        jest.runAllTimers();

        expect(wrapper.vm.isInsideComponent).toBeTruthy();

        contentComponent.dispatchEvent(new Event('mouseleave'));
        jest.runAllTimers();

        expect(wrapper.vm.isInsideComponent).toBeFalsy();
    });
});
