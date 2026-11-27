/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

describe('module/sw-settings-system-health/component/sw-settings-system-health-section', () => {
    it('should show its title, action and content', async () => {
        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-section', { sync: true }), {
            props: { title: 'Details' },
            slots: { default: '<p>content</p>', action: '<button>Resolve</button>' },
        });

        expect(wrapper.find('.sw-settings-system-health-section__title').text()).toBe('Details');
        expect(wrapper.find('button').text()).toBe('Resolve');
        expect(wrapper.find('p').text()).toBe('content');
    });
});
