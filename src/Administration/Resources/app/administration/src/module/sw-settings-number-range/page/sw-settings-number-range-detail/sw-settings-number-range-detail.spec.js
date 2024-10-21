/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-number-range-detail', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        params: {
                            id: 'id',
                        },
                    },
                },
                provide: {
                    numberRangeService: {
                        previewPattern: () => Promise.resolve({ number: 1337 }),
                    },
                    repositoryFactory: {
                        create: () => ({
                            create: () => {},
                            get: () =>
                                Promise.resolve({
                                    description: null,
                                    global: true,
                                    id: 'id',
                                    name: 'Delivery notes',
                                    translated: {
                                        customFields: [],
                                        description: null,
                                        name: 'Delivery notes',
                                    },
                                    translations: [],
                                    type: {
                                        typeName: 'Delivery notes',
                                    },
                                    typeId: '72ea130130404f67a426332f7a8c7277',
                                }),
                            search: () => Promise.resolve([]),
                        }),
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions" />
                        <slot name="content" />
                        <slot />
                    </div>`,
                    },
                    'sw-button': {
                        template: '<div class="sw-button"><slot /></div>',
                        props: ['disabled'],
                    },
                    'sw-button-process': {
                        template: '<div class="sw-button-process"><slot /></div>',
                        props: ['disabled'],
                    },
                    'sw-card': {
                        template: '<div class="sw-card"><slot /></div>',
                    },
                    'sw-switch-field': true,
                    'sw-number-field': true,
                    'sw-text-field': {
                        template: '<div class="sw-field"></div>',
                        props: ['disabled'],
                    },
                    'sw-card-view': {
                        template: '<div><slot /></div>',
                    },
                    'sw-container': true,
                    'sw-language-info': true,
                    'sw-help-text': true,
                    'sw-multi-select': true,
                    'sw-entity-single-select': {
                        template: '<div class="sw-entity-single-select"></div>',
                        props: ['disabled'],
                    },
                    'sw-alert': true,
                    'sw-skeleton': true,
                    'sw-language-switch': true,
                    'sw-custom-field-set-renderer': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-number-range/page/sw-settings-number-range-detail', () => {
    beforeEach(async () => {
        global.activeAclRoles = [];
    });

    it('should call to numberRangeService.previewPattern when has numberRange.technicalName when get preview', async () => {
        const wrapper = await createWrapper();

        const previewPatternMock = jest.fn(() => Promise.resolve({ number: 42 }));
        wrapper.vm.numberRangeService.previewPattern = previewPatternMock;

        await wrapper.setData({
            numberRange: {
                type: {
                    technicalName: 'test',
                },
            },
        });

        await wrapper.vm.getPreview();

        expect(previewPatternMock).toHaveBeenCalled();
    });

    it('should not call to numberRangeService.previewPattern when has numberRange.technicalName when get preview', async () => {
        const wrapper = await createWrapper();

        const previewPatternMock = jest.fn(() => Promise.resolve({ number: 42 }));
        wrapper.vm.numberRangeService.previewPattern = previewPatternMock;

        await wrapper.vm.getPreview();

        expect(previewPatternMock).not.toHaveBeenCalled();
    });

    it('should call to numberRangeService.previewPattern when has numberRange.technicalName when get state', async () => {
        const wrapper = await createWrapper();

        const previewPatternMock = jest.fn(() => Promise.resolve({ number: 42 }));
        wrapper.vm.numberRangeService.previewPattern = previewPatternMock;

        await wrapper.setData({
            numberRange: {
                type: {
                    technicalName: 'test',
                },
            },
        });

        await wrapper.vm.getState();

        expect(previewPatternMock).toHaveBeenCalled();
    });

    it('should not call to numberRangeService.previewPattern when has numberRange.technicalName when get state', async () => {
        const wrapper = await createWrapper();

        const previewPatternMock = jest.fn(() => Promise.resolve({ number: 42 }));
        wrapper.vm.numberRangeService.previewPattern = previewPatternMock;

        await wrapper.vm.getState();

        expect(previewPatternMock).not.toHaveBeenCalled();
    });

    it('should not be able to save the number range', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.findComponent('.sw-settings-number-range-detail__save-action');

        expect(saveButton.props('disabled')).toBe(true);
        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true,
        });
    });

    it('should be able to save the number range', async () => {
        global.activeAclRoles = ['number_ranges.editor'];

        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.findComponent('.sw-settings-number-range-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);
        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light',
        });
    });

    it('should be able to edit the number range', async () => {
        global.activeAclRoles = ['number_ranges.editor'];

        const wrapper = await createWrapper();

        await wrapper.setData({ isLoading: false });
        await flushPromises();

        const alwaysDisabledElements = [
            'sw-settings-number-range.detail.labelCurrentNumber',
            'sw-settings-number-range.detail.labelPreview',
            'sw-settings-number-range.detail.labelSuffix',
            'sw-settings-number-range.detail.labelPrefix',
        ];

        const elements = wrapper.findAllComponents('.sw-field');

        elements.forEach((el) => {
            const isAlwaysDisabled = alwaysDisabledElements.includes(el.attributes('label'));

            expect(el.props('disabled')).toBe(isAlwaysDisabled);
        });

        const numberRangeType = wrapper.findComponent('#numberRangeTypes');
        expect(numberRangeType.props('disabled')).toBe(true);
    });

    it('should not be able to edit the number range', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({ isLoading: false });
        await flushPromises();

        const elements = wrapper.findAllComponents('.sw-field');
        elements.forEach((el) => expect(el.props('disabled')).toBe(true));

        const numberRangeType = wrapper.findComponent('#numberRangeTypes');
        expect(numberRangeType.props('disabled')).toBe(true);
    });
});
