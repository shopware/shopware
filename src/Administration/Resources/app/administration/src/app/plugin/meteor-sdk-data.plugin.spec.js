/**
 * @package admin
 */

import MeteorSdkDataPlugin from 'src/app/plugin/meteor-sdk-data.plugin';
import { mount, config } from '@vue/test-utils';

describe('src/app/plugin/meteor-sdk-data.plugin.ts', () => {
    let orgPlugins = [];

    beforeAll(() => {
        // preserve org plugins
        orgPlugins = config.global.plugins;

        // unset global plugins to remove a warning of a doubled registered plugin
        config.global.plugins = [];
    });

    afterAll(() => {
        // restore original global plugins
        config.global.plugins = orgPlugins;
    });

    it('should add and call dataSetUnwatchers to the component instance', () => {
        const wrapper = mount(
            {
                template: '<div>jest</div>',
            },
            {
                global: {
                    plugins: [
                        MeteorSdkDataPlugin,
                    ],
                },
            },
        );

        // template should render normal and have dataSetUnwatchers property on the instance
        expect(wrapper.text()).toBe('jest');
        expect(wrapper.vm.dataSetUnwatchers).toStrictEqual([]);

        // push mock into unwatchers
        const mock = jest.fn();
        wrapper.vm.dataSetUnwatchers.push(mock);

        // Unmount component and expect mock to be called
        wrapper.unmount();
        expect(mock).toHaveBeenCalledTimes(1);
    });
});
