/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-config-html', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
                stubs: {
                    'sw-code-editor': true,
                    'sw-alert': true,
                },
            },
            props: {
                element: {
                    config: {
                        content: {
                            value: 'Test',
                        },
                    },
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/html/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should update element onBlur', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onBlur('Foo');
        expect(wrapper.vm.element.config.content.value).toBe('Foo');
        expect(wrapper.emitted('element-update')).toBeTruthy();
    });

    it('should not update element onInput', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onInput('Test');
        expect(wrapper.vm.element.config.content.value).toBe('Test');
        expect(wrapper.emitted('element-update')).toBeFalsy();
    });
});
