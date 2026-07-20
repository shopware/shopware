/**
 * @sw-package after-sales
 */
import { shallowMount } from '@vue/test-utils';
import swMailTemplateViewHeaderFooter from 'src/module/sw-mail-template/view/sw-mail-template-view-header-footer';

Shopware.Component.register('sw-mail-template-view-header-footer', swMailTemplateViewHeaderFooter);

describe('module/sw-mail-template/view/sw-mail-template-view-header-footer', () => {
    it('should render the mail header footer list', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-mail-template-view-header-footer'), {
            global: {
                stubs: {
                    'sw-mail-header-footer-list': true,
                },
            },
        });

        expect(wrapper.findComponent({ name: 'sw-mail-header-footer-list' }).exists()).toBe(true);
    });
});
