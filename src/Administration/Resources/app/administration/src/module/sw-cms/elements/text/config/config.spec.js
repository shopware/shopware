/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-text', { sync: true }), {
        global: {
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { text: {} };
                    },
                },
            },
            stubs: {
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-tabs': await wrapTestComponent('sw-tabs', {
                    sync: true,
                }),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item', {
                    sync: true,
                }),
                'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field', { sync: true }),
                'sw-text-editor': {
                    props: ['value'],
                    emits: [
                        'blur',
                        'update:value',
                        'change',
                    ],
                    template:
                        '<input type="text" :value="value" @blur="$emit(\'blur\', $event.target.value)" @input="$emit(\'update:value\', $event.target.value)" @change="$emit(\'change\', $event.target.value)"></input>',
                },
                'sw-select-field': true,
                'sw-icon': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-alert': true,
            },
        },
        props: {
            element: {
                config: {
                    content: {
                        value: '',
                    },
                },
            },
        },
    });
}

describe('src/module/sw-cms/elements/text/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('should emits element-update when trigger @input event', async () => {
        const wrapper = await createWrapper();

        const updatedContent = 'Updated content';

        const input = wrapper.find('input[type="text"]');
        await input.setValue(updatedContent);

        expect(input.element.value).toBe(updatedContent);

        await input.trigger('input');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.content.value).toBe(updatedContent);
        expect(wrapper.emitted('element-update')).toBeTruthy();
        expect(wrapper.emitted()['element-update'][0][0]).toEqual(wrapper.vm.element);
    });

    it('should emits element-update when trigger @blur event', async () => {
        const wrapper = await createWrapper();

        const updatedContent = 'Updated content';

        const input = wrapper.find('input[type="text"]');
        await input.setValue(updatedContent);

        expect(input.element.value).toBe(updatedContent);

        await input.trigger('blur');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.content.value).toBe(updatedContent);
        expect(wrapper.emitted('element-update')).toBeTruthy();
        expect(wrapper.emitted()['element-update'][0][0]).toEqual(wrapper.vm.element);
    });
});
