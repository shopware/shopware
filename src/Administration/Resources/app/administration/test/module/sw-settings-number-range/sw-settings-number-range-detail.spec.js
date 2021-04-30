import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-number-range/page/sw-settings-number-range-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-number-range-detail'), {
        localVue,
        mocks: {
            $route: {
                params: {
                    id: 'id'
                }
            }
        },
        provide: {
            numberRangeService: {
                previewPattern: () => Promise.resolve()
            },
            repositoryFactory: {
                create: () => ({
                    create: () => {},
                    get: () => Promise.resolve({
                        description: null,
                        global: true,
                        id: 'id',
                        name: 'Delivery notes',
                        translated: {
                            customFields: [],
                            description: null,
                            name: 'Delivery notes'
                        },
                        translations: [],
                        type: {
                            typeName: 'Delivery notes'
                        },
                        typeId: '72ea130130404f67a426332f7a8c7277'
                    }),
                    search: () => Promise.resolve([])
                })
            },
            acl: {
                can: key => (key ? privileges.includes(key) : true)
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions" />
                        <slot name="content" />
                        <slot />
                    </div>`
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-card': {
                template: '<div class="sw-card"><slot /></div>'
            },
            'sw-field': {
                template: '<div class="sw-field"/>'
            },
            'sw-card-view': {
                template: '<div><slot /></div>'
            },
            'sw-container': true,
            'sw-language-info': true,
            'sw-help-text': true,
            'sw-multi-select': true,
            'sw-entity-single-select': true,
            'sw-alert': true
        }
    });
}

describe('src/module/sw-settings-number-range/page/sw-settings-number-range-detail', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to save the number range', async () => {
        const saveButton = wrapper.find('.sw-settings-number-range-detail__save-action');

        await wrapper.vm.$nextTick();

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true
        });
    });

    it('should be able to save the number range', async () => {
        wrapper = createWrapper([
            'number_ranges.editor'
        ]);

        await wrapper.setData({
            isLoading: false
        });

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-number-range-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light'
        });
    });

    it('should be able to edit the number range', async () => {
        wrapper = await createWrapper([
            'number_ranges.editor'
        ]);

        await wrapper.vm.$nextTick();

        const elements = wrapper.findAll('.sw-field');
        elements.wrappers.forEach(el => {
            if ([
                'sw-settings-number-range.detail.labelCurrentNumber',
                'sw-settings-number-range.detail.labelPreview',
                'sw-settings-number-range.detail.labelSuffix',
                'sw-settings-number-range.detail.labelPrefix'
            ].includes(el.attributes().label)) {
                expect(el.attributes().disabled).toBe('disabled');
                return;
            }

            expect(el.attributes().disabled).toBeUndefined();
        });

        const numberRangeType = wrapper.find('#numberRangeTypes');
        expect(numberRangeType.attributes().disabled).toBeTruthy();
    });

    it('should not be able to edit the number range', async () => {
        await wrapper.vm.$nextTick();

        const elements = wrapper.findAll('.sw-field');
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        const numberRangeType = wrapper.find('#numberRangeTypes');
        expect(numberRangeType.attributes().disabled).toBeTruthy();
    });
});
