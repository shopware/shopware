/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(additionalStubs = {}) {
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
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: ['new-item-active'],
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                    template: '<div class="mt-tabs"></div>',
                },
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
                'mt-select': {
                    template:
                        '<select class="mt-select" :value="modelValue" @change="$emit(`update:modelValue`, $event.target.value)"></select>',
                    props: [
                        'modelValue',
                        'options',
                        'disabled',
                    ],
                },
                'sw-select-field': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-cms-inherit-wrapper': {
                    template: '<div><slot :isInherited="false"></slot></div>',
                    props: [
                        'field',
                        'element',
                        'contentEntity',
                        'label',
                    ],
                },
                ...additionalStubs,
            },
        },
        props: {
            element: {
                config: {
                    content: {
                        value: '',
                    },
                    verticalAlign: {
                        value: null,
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

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy CMS text tabs.
    it.deprecated('v6.8.0.0')('should render deprecated tabs', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs', async () => {
        const wrapper = await createWrapper({});
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-cms-element-config-text');
        expect(tabs.props('defaultItem')).toBe('content');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-cms.elements.general.config.tab.content',
                name: 'content',
            },
            {
                label: 'sw-cms.elements.general.config.tab.settings',
                name: 'settings',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-text__tab-content').exists()).toBe(true);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should switch meteor tab content when the active tab changes', async () => {
        const wrapper = await createWrapper({});
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        await tabs.vm.$emit('new-item-active', 'settings');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('settings');
        expect(wrapper.find('.sw-cms-el-config-text__tab-content').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-text__tab-settings').exists()).toBe(true);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy CMS text editor.
    it.deprecated('v6.8.0.0')('should emits element-update when trigger @input event', async () => {
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

    it.activeFeatureFlags([
        'v6.8.0.0',
        'METEOR_TEXT_EDITOR',
    ])('should emits element-update when trigger @input event', async () => {
        const wrapper = await createWrapper({
            'mt-text-editor': {
                props: ['modelValue'],
                emits: ['update:modelValue'],
                template:
                    '<input class="mt-text-editor-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
            },
        });

        const updatedContent = 'Updated content';
        const input = wrapper.get('.mt-text-editor-input');

        await input.setValue(updatedContent);
        await flushPromises();

        expect(wrapper.vm.element.config.content.value).toBe(updatedContent);
        expect(wrapper.emitted('element-update')).toBeTruthy();
        expect(wrapper.emitted()['element-update'][0][0]).toEqual(wrapper.vm.element);
    });

    // Covers the default major-suite combination: the v6.8 meteor tabs still render the legacy
    // sw-text-editor because METEOR_TEXT_EDITOR is a separate, non-major flag. Remove with sw-text-editor.
    it.activeFeatureFlags(['v6.8.0.0'])(
        'should emit element-update on @input from the legacy editor under the meteor tabs',
        async () => {
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
        },
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-text-editor blur integration.
    it.deprecated('v6.8.0.0')('should emits element-update when trigger @blur event', async () => {
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

    // Covers the default major-suite combination (v6.8 tabs + legacy editor, METEOR_TEXT_EDITOR off).
    // Remove with sw-text-editor.
    it.activeFeatureFlags(['v6.8.0.0'])(
        'should emit element-update on @blur from the legacy editor under the meteor tabs',
        async () => {
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
        },
    );

    describe('handleUpdateContent', () => {
        it('should return true when textEditor ref is not available', async () => {
            const wrapper = await createWrapper({
                'mt-text-editor': {
                    template: '<div></div>',
                },
            });

            const result = await wrapper.vm.handleUpdateContent();

            expect(result).toBe(true);
        });

        it.activeFeatureFlags([
            'v6.8.0.0',
            'METEOR_TEXT_EDITOR',
        ])('should delegate to textEditor.validate and return true on success', async () => {
            const mockValidate = jest.fn(() => Promise.resolve(true));

            const wrapper = await createWrapper({
                'mt-text-editor': {
                    template: '<div></div>',
                    methods: { validate: mockValidate },
                },
            });
            await flushPromises();

            const result = await wrapper.vm.handleUpdateContent();

            expect(mockValidate).toHaveBeenCalledTimes(1);
            expect(result).toBe(true);
        });

        it.activeFeatureFlags([
            'v6.8.0.0',
            'METEOR_TEXT_EDITOR',
        ])('should return false when textEditor.validate reports invalid content', async () => {
            const mockValidate = jest.fn(() => Promise.resolve(false));

            const wrapper = await createWrapper({
                'mt-text-editor': {
                    template: '<div></div>',
                    methods: { validate: mockValidate },
                },
            });
            await flushPromises();

            const result = await wrapper.vm.handleUpdateContent();

            expect(mockValidate).toHaveBeenCalledTimes(1);
            expect(result).toBe(false);
        });
    });
});
