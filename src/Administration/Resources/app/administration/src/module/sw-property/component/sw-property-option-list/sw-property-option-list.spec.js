/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';

function getOptions() {
    const options = [
        {
            groupId: '0d976ffa3ade4b618b538818ddd043f7',
            name: 'oldgold',
            position: 1,
            colorHexCode: '#dd7373',
            mediaId: null,
            customFields: null,
            createdAt: '2020-06-23T13:38:40+00:00',
            updatedAt: '2020-06-23T13:44:26+00:00',
            translated: { name: 'oldgold', position: 1, customFields: [] },
            apiAlias: null,
            id: '012a7cac453e496389d0d76a3c460cfe',
            translations: [],
            productConfiguratorSettings: [],
            productProperties: [],
            productOptions: [],
        },
    ];

    options.criteria = {
        page: 1,
        limit: 25,
    };

    return options;
}

const propertyGroup = {
    name: 'color',
    description: null,
    displayType: 'text',
    sortingType: 'alphanumeric',
    position: 1,
    customFields: null,
    createdAt: '2020-06-23T13:38:40+00:00',
    updatedAt: '2020-06-23T13:44:26+00:00',
    translated: {
        name: 'color',
        description: null,
        position: 1,
        customFields: [],
    },
    apiAlias: null,
    id: '0d976ffa3ade4b618b538818ddd043f7',
    options: getOptions(),
    translations: [],
    _isNew: false,
    isNew() {
        return this._isNew;
    },
};

function getOptionRepository() {
    return {
        create: () => ({
            get: () => Promise.resolve(),
        }),
        save: jest.fn(() => Promise.resolve()),
    };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-property-option-list', { sync: true }), {
        props: {
            propertyGroup: propertyGroup,
            optionRepository: getOptionRepository(),
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: () => Promise.resolve(),
                        save: jest.fn(() => Promise.resolve()),
                        search: () => Promise.resolve({ propertyGroup }),
                    }),
                },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
            stubs: {
                'sw-card': await wrapTestComponent('sw-card', { sync: true }),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-ignore-class': true,
                'sw-container': await wrapTestComponent('sw-container', { sync: true }),
                'sw-button': {
                    template: '<button class="sw-button" @click="$emit(`click`)"></botton>',
                },
                'sw-simple-search-field': {
                    template: '<div></div>',
                },
                'sw-one-to-many-grid': await wrapTestComponent('sw-one-to-many-grid', { sync: true }),
                'sw-pagination': {
                    template: '<div></div>',
                },
                'sw-checkbox-field': {
                    template: '<div></div>',
                },
                'sw-context-button': {
                    template: '<div></div>',
                },
                'sw-icon': {
                    template: '<div></div>',
                },
                'sw-property-option-detail': await wrapTestComponent('sw-property-option-detail', { sync: true }),
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                            <slot></slot>

                            <div class="modal-footer">
                                <slot name="modal-footer"></slot>
                            </div>
                        </div>
                `,
                },
                'sw-colorpicker': {
                    template: `
                    <input class="sw-colorpicker-stub"
                        :value="value" type="color"
                        @input="$emit(\'update:value\', $event.target.value)"/>
                    `,
                    props: ['value'],
                    emits: ['update:value'],
                },
                'sw-upload-listener': {
                    template: '<div></div>',
                },
                'sw-media-compact-upload-v2': {
                    template: '<div></div>',
                },
                'sw-number-field': {
                    template: `
                        <input class="sw-number-field-stub"
                            :value="value" type="number"
                            @input="$emit(\'update:value\', $event.target.value)"/>
                    `,
                    props: ['value'],
                    emits: ['update:value'],
                },
                'sw-text-field': {
                    template: `
                        <input class="sw-text-field-stub"
                            :value="value" type="text"
                            @input="$emit(\'update:value\', $event.target.value)"/>
                    `,
                    props: ['value'],
                    emits: ['update:value'],
                },
                'sw-contextual-field': {
                    template: '<div></div>',
                },
                'sw-extension-component-section': true,
            },
        },
    });
}

describe('module/sw-property/component/sw-property-option-list', () => {
    it('should update property values after saving the changes in the modal', async () => {
        global.activeAclRoles = ['property.editor'];

        const wrapper = await createWrapper();

        const initialHexCodeValue = wrapper.find('.sw-data-grid__cell--colorHexCode span').text();

        expect(initialHexCodeValue).toBe('#dd7373');

        await wrapper.find('.sw-settings-option-detail__link').trigger('click');

        // waiting for modal to be loaded
        await wrapper.vm.$nextTick();

        const modal = wrapper.find('.sw-modal');

        // clear color value
        await modal.get('.sw-text-field-stub').setValue('new name');
        await modal.get('.sw-number-field-stub').setValue(0);
        await modal.get('.sw-colorpicker-stub').setValue('#000000');

        await modal.find('button[variant="primary"]').trigger('click');

        // waiting for the modal to disappear
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.optionRepository.save).toHaveBeenCalledWith(expect.objectContaining({
            name: 'new name',
            position: '0',
            colorHexCode: '#000000',
        }));

        expect(wrapper.find('.modal').exists()).toBe(false);
    });
});
