/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import swCmsElConfigText from 'src/module/sw-cms/elements/text/config';
import swCmsMappingField from 'src/module/sw-cms/component/sw-cms-mapping-field';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

Shopware.Component.register('sw-cms-el-config-text', swCmsElConfigText);
Shopware.Component.register('sw-cms-mapping-field', swCmsMappingField);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-el-config-text'), {
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
            'sw-container': true,
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'sw-cms-mapping-field': await Shopware.Component.build('sw-cms-mapping-field'),
            'sw-text-editor': {
                props: ['value'],
                template: '<input type="text" :value="value" @change="$emit(\'change\', $event.target.value)"></input>',
            },
        },
        propsData: {
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

describe('src/module/sw-cms/elements/image/config', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                currentMappingTypes: {},
            },
        });
    });

    it('should update the content data property', async () => {
        const wrapper = await createWrapper();

        const newContent = 'New content';

        wrapper.vm.emitChanges(newContent);

        expect(wrapper.vm.content).toBe(newContent);
    });

    it('calls handleUpdateContent method and emits element-update event', async () => {
        const wrapper = await createWrapper();
        const updatedContent = 'Updated content';

        wrapper.vm.content = updatedContent;

        wrapper.vm.handleUpdateContent();

        expect(wrapper.vm.element.config.content.value).toBe(updatedContent);

        expect(wrapper.emitted('element-update')).toBeTruthy();
        expect(wrapper.emitted('element-update')[0][0]).toEqual(wrapper.vm.element);
    });
});
