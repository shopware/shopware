/**
 * @package admin
 */

import VirtualCallStackPlugin from 'src/app/plugin/virtual-call-stack.plugin';
import { mount, config } from '@vue/test-utils';

describe('src/app/plugin/virtual-call-stack.plugin.ts', () => {
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

    it('should add _virtualCallStack to the component instance', () => {
        const wrapper = mount(
            {
                template: '<div>jest</div>',
            },
            {
                global: {
                    plugins: [
                        VirtualCallStackPlugin,
                    ],
                },
            },
        );

        expect(wrapper.text()).toBe('jest');
        expect(wrapper.vm._virtualCallStack).toStrictEqual({});
    });
});
