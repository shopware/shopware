/**
 * @package admin
 * @group disabledCompat
 */

import VirtualCallStackPlugin from 'src/app/plugin/virtual-call-stack.plugin';
import { createLocalVue, mount } from '@vue/test-utils';

describe('src/app/plugin/virtual-call-stack.plugin.ts', () => {
    it('should add _virtualCallStack to the component instance', () => {
        const localVue = createLocalVue();
        localVue.use(VirtualCallStackPlugin);
        const wrapper = mount({
            template: '<div>jest</div>',
        }, { localVue });

        expect(wrapper.text()).toBe('jest');
        expect(wrapper.vm._virtualCallStack).toStrictEqual({});
    });
});
