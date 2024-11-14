/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import metaInfoPlugin from 'src/app/plugin/meta-info.plugin';

const createComponent = ({ customComponent, customOptions } = {}) => {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...customComponent,
    };

    return mount(baseComponent, {
        global: {
            plugins: [metaInfoPlugin],
        },
        ...customOptions,
    });
};

describe('app/plugins/meta-info.plugin', () => {
    afterEach(() => {
        document.title = '';
        metaInfoPlugin.pluginInstalled = false;
    });

    it('should be a Vue.js component', async () => {
        const component = createComponent();

        expect(component.vm).toBeTruthy();
        expect(metaInfoPlugin.isMetaInfoPluginInstalled()).toBe(true);
    });

    it('should throw a warning if the plugin gets registered twice', async () => {
        global.console.warn = jest.fn();
        metaInfoPlugin.install({ mixin: jest.fn() });

        createComponent();

        expect(global.console.warn).toHaveBeenCalledWith('[Meta Info Plugin]', 'This plugin is already installed');
        global.console.warn.mockReset();
    });

    it('should change the document title according to the metaInfo title', async () => {
        createComponent({
            customComponent: {
                metaInfo() {
                    return {
                        title: 'Jest title',
                    };
                },
            },
        });

        expect(document.title).toBe('Jest title');
    });

    it('should throw a warning if the metaInfo is an object', async () => {
        global.console.warn = jest.fn();
        createComponent({
            customComponent: {
                metaInfo: {
                    title: 'Jest title',
                },
            },
        });

        expect(document.title).toBe('');
        expect(global.console.warn).toHaveBeenCalledWith(
            '[Meta Info Plugin]',
            'Providing the metaInfo as an object is not supported anymore. Please use a function instead.',
        );
        global.console.warn.mockReset();
    });
});
