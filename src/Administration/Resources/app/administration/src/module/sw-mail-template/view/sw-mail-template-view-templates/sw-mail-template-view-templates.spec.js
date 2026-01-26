/**
 * @sw-package after-sales
 */
import { shallowMount } from '@vue/test-utils';
import swMailTemplateViewTemplates from 'src/module/sw-mail-template/view/sw-mail-template-view-templates';

Shopware.Component.register('sw-mail-template-view-templates', swMailTemplateViewTemplates);

describe('module/sw-mail-template/view/sw-mail-template-view-templates', () => {
    it('should render the mail template list', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-mail-template-view-templates'), {
            global: {
                stubs: {
                    'sw-mail-template-list': true,
                },
            },
        });

        expect(wrapper.findComponent({ name: 'sw-mail-template-list' }).exists()).toBe(true);
    });
});
