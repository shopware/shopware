/**
 * @package buyers-experience
 */
import { createLocalVue, shallowMount } from '@vue/test-utils_v2';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import swCmsBlockConfig from './index';
import 'src/app/component/form/sw-colorpicker';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-text-field';

const { Component, State } = Shopware;

Component.register('sw-cms-block-config', swCmsBlockConfig);

const block = {
    name: 'Block name',
    backgroundColor: '',
    backgroundMedia: {},
    backgroundMediaId: 'mediaId',
    backgroundMediaMode: '',
};

jest.useFakeTimers();

async function createWrapper() {
    const localVue = createLocalVue();
    return shallowMount(await Component.build('sw-cms-block-config'), {
        localVue,
        propsData: {
            block,
        },
        provide: {
            validationService: {},
            cmsService: {
                getCmsBlockRegistry: () => {
                    return Promise.resolve();
                },
            },
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return Promise.resolve();
                    },
                }),
            },
        },
        stubs: {
            'sw-base-field': await Component.build('sw-base-field'),
            'sw-colorpicker': await Component.build('sw-colorpicker'),
            'sw-contextual-field': await Component.build('sw-contextual-field'),
            'sw-block-field': await Component.build('sw-block-field'),
            'sw-field-error': true,
            'sw-icon': true,
            'sw-text-field': {
                template: '<input class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />',
                props: ['value'],
            },
            'sw-media-compact-upload-v2': true,
            'sw-upload-listener': true,
            'sw-select-field': true,
            'sw-help-text': true,
        },
    });
}

describe('module/sw-cms/component/sw-cms-block-config', () => {
    beforeEach(() => {
        if (State.get('cmsPageState')) {
            State.unregisterModule('cmsPageState');
        }

        State.registerModule('cmsPageState', {
            namespaced: true,
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should able to config block name', async () => {
        const wrapper = await createWrapper();
        const blockNameField = await wrapper.find('.sw-text-field');

        expect(wrapper.vm.block.name).toBe(block.name);
        await blockNameField.setValue('test');
        await blockNameField.trigger('input');

        jest.runAllTimers();
        expect(wrapper.vm.block.name).toBe('test');
    });

    it('should able to remove all media', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.block.backgroundMediaId).toBe(block.backgroundMediaId);
        await wrapper.vm.removeMedia();
        expect(wrapper.vm.block.backgroundMediaId).toBeNull();
    });
});
