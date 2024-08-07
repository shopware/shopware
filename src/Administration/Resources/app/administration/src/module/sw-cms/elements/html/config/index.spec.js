/**
 * @package buyers-experience
 * @group disabledCompat
 */

import 'src/module/sw-cms/service/cms.service';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-html', {
        sync: true,
    }), {
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
    });
}

describe('src/module/sw-cms/elements/html/config/index.js', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPageState',
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should update element onBlur', () => {
        wrapper.vm.onBlur('Foo');
        expect(wrapper.vm.element.config.content.value).toBe('Foo');
        expect(wrapper.emitted('element-update')).toBeTruthy();
    });

    it('should not update element onInput', () => {
        wrapper.vm.onInput('Test');
        expect(wrapper.vm.element.config.content.value).toBe('Test');
        expect(wrapper.emitted('element-update')).toBeFalsy();
    });
});
