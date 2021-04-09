import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-delivery-time-detail'), {
        localVue,
        mocks: {
            $route: {
                params: {
                    id: '1'
                }
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {
                            name: '',
                            min: 0,
                            max: 0,
                            unit: '',
                            isNew: () => true
                        };
                    },

                    get: (id) => {
                        const deliveryTimes = [
                            {
                                id: '1',
                                name: '1 - 3 weeks',
                                min: 1,
                                max: 3,
                                unit: 'week',
                                isNew: () => false
                            },
                            {
                                id: 2,
                                name: '2 - 5 days',
                                min: 2,
                                max: 5,
                                unit: 'day',
                                isNew: () => false
                            }
                        ];

                        return Promise.resolve(deliveryTimes.find((deliveryTime) => {
                            return deliveryTime.id === id;
                        }));
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-language-switch': true,
            'sw-card-view': true,
            'sw-card': true,
            'sw-container': true,
            'sw-field': true,
            'sw-number-field': true,
            'sw-language-info': true,
            'sw-single-select': true
        }
    });
}

describe('src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-detail', () => {
    it('should not be able to save the delivery time', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-delivery-time-detail__save');
        const nameField = wrapper.find('sw-field-stub[label="sw-settings-delivery-time.detail.labelName"]');
        const maxNumberField = wrapper.find('sw-number-field-stub[label="sw-settings-delivery-time.detail.labelMax"]');
        const minNumberField = wrapper.find('sw-number-field-stub[label="sw-settings-delivery-time.detail.labelMin"]');
        const unitSingleSelect = wrapper.find('sw-single-select-stub[label="sw-settings-delivery-time.detail.labelUnit"]');

        expect(nameField.attributes().disabled).toBeTruthy();
        expect(maxNumberField.attributes().disabled).toBeTruthy();
        expect(minNumberField.attributes().disabled).toBeTruthy();
        expect(unitSingleSelect.attributes().disabled).toBeTruthy();

        expect(saveButton.attributes().disabled).toBeTruthy();

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true
        });
    });

    it('should be able to save the delivery time', async () => {
        const wrapper = createWrapper([
            'delivery_times.editor'
        ]);

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-delivery-time-detail__save');
        const nameField = wrapper.find('sw-field-stub[label="sw-settings-delivery-time.detail.labelName"]');
        const maxNumberField = wrapper.find('sw-number-field-stub[label="sw-settings-delivery-time.detail.labelMax"]');
        const minNumberField = wrapper.find('sw-number-field-stub[label="sw-settings-delivery-time.detail.labelMin"]');
        const unitSingleSelect = wrapper.find('sw-single-select-stub[label="sw-settings-delivery-time.detail.labelUnit"]');

        expect(nameField.attributes().disabled).toBeFalsy();
        expect(maxNumberField.attributes().disabled).toBeFalsy();
        expect(minNumberField.attributes().disabled).toBeFalsy();
        expect(unitSingleSelect.attributes().disabled).toBeFalsy();

        expect(saveButton.attributes().disabled).toBeFalsy();

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light'
        });
    });

    it('should be able to create new delivery time', async () => {
        const wrapper = createWrapper([
            'delivery_times.creator',
            'delivery_times.editor'
        ]);

        await wrapper.vm.$nextTick();

        // Assume that user navigate to sw-setting-delivery-time-create page
        await wrapper.setData({ deliveryTime: wrapper.vm.deliveryTimeRepository.create() });

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-delivery-time-detail__save');
        const nameField = wrapper.find('sw-field-stub[label="sw-settings-delivery-time.detail.labelName"]');
        const maxNumberField = wrapper.find('sw-number-field-stub[label="sw-settings-delivery-time.detail.labelMax"]');
        const minNumberField = wrapper.find('sw-number-field-stub[label="sw-settings-delivery-time.detail.labelMin"]');
        const unitSingleSelect = wrapper.find('sw-single-select-stub[label="sw-settings-delivery-time.detail.labelUnit"]');

        expect(nameField.attributes().disabled).toBeFalsy();
        expect(maxNumberField.attributes().disabled).toBeFalsy();
        expect(minNumberField.attributes().disabled).toBeFalsy();
        expect(unitSingleSelect.attributes().disabled).toBeFalsy();

        expect(saveButton.attributes().disabled).toBeFalsy();

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light'
        });
    });
});

