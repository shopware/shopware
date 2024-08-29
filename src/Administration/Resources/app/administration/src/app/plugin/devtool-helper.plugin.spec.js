/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import devtoolHelperPlugin from 'src/app/plugin/devtool-helper.plugin';

const createComponent = ({ customComponent, customOptions } = {}) => {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...customComponent,
    };

    return mount(baseComponent, {
        global: {
            plugins: [devtoolHelperPlugin],
        },
        ...customOptions,
    });
};

describe('app/plugins/devtool-helper.plugin', () => {
    beforeEach(() => {
        window._sw_extension_component_collection = undefined;
    });

    it('should be a Vue.js component', async () => {
        const component = createComponent();

        expect(component.vm).toBeTruthy();
    });

    it('should not add the component to the collection when no extensionApiDevtoolInformation exists', async () => {
        createComponent();

        await flushPromises();

        expect(window._sw_extension_component_collection).toBeUndefined();
    });

    it('should add the component to the collection when extensionApiDevtoolInformation exists', async () => {
        const component = createComponent({
            customComponent: {
                extensionApiDevtoolInformation: {
                    property: 'ui.componentSection',
                    method: 'add',
                    positionId: (currentComponent) => {
                        return currentComponent.positionIdentifier;
                    },
                },
            },
        });

        await flushPromises();

        expect(window._sw_extension_component_collection).toHaveLength(1);
        expect(window._sw_extension_component_collection[0]).toBe(component.vm);
    });

    it('should remove the component from the collection before unmount', async () => {
        const component = createComponent({
            customComponent: {
                extensionApiDevtoolInformation: {
                    property: 'ui.componentSection',
                    method: 'add',
                    positionId: (currentComponent) => {
                        return currentComponent.positionIdentifier;
                    },
                },
            },
        });

        await flushPromises();

        expect(window._sw_extension_component_collection).toHaveLength(1);

        component.unmount();
        await flushPromises();

        expect(window._sw_extension_component_collection).toHaveLength(0);
    });
});
