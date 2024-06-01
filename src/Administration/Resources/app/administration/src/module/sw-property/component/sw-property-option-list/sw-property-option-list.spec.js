/*
 * @package inventory
 */

import { shallowMount } from '@vue/test-utils';
import swPropertyOptionList from 'src/module/sw-property/component/sw-property-option-list';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-container';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/entity/sw-one-to-many-grid';
import swPropertyOptionDetail from 'src/module/sw-property/component/sw-property-option-detail';
import 'src/app/component/form/sw-colorpicker';

Shopware.Component.register('sw-property-option-list', swPropertyOptionList);
Shopware.Component.register('sw-property-option-detail', swPropertyOptionDetail);

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
        save: () => Promise.resolve(),
    };
}

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-property-option-list'), {
        propsData: {
            propertyGroup: propertyGroup,
            optionRepository: getOptionRepository(),
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => Promise.resolve(),
                    save: () => Promise.resolve(),
                    search: () => Promise.resolve({ propertyGroup }),
                }),
            },
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {},
            },
            searchRankingService: {},
        },
        stubs: {
            'sw-card': await Shopware.Component.build('sw-card'),
            'sw-ignore-class': true,
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-button': {
                template: '<div></div>',
            },
            'sw-simple-search-field': {
                template: '<div></div>',
            },
            'sw-one-to-many-grid': await Shopware.Component.build('sw-one-to-many-grid'),
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
            'sw-property-option-detail': await Shopware.Component.build('sw-property-option-detail'),
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
            'sw-colorpicker': await Shopware.Component.build('sw-colorpicker'),
            'sw-upload-listener': {
                template: '<div></div>',
            },
            'sw-media-compact-upload-v2': {
                template: '<div></div>',
            },
            'sw-number-field': {
                template: '<div></div>',
            },
            'sw-text-field': {
                template: '<div></div>',
            },
            'sw-contextual-field': {
                template: '<div></div>',
            },
            'sw-extension-component-section': true,
        },
    });
}

describe('module/sw-property/component/sw-property-option-list', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get rid of color value', async () => {
        const initalHexCodeValue = wrapper.find('.sw-data-grid__cell--colorHexCode span').text();

        expect(initalHexCodeValue).toBe('#dd7373');

        // open modal by setting the current selected option
        wrapper.vm.currentOption = wrapper.vm.propertyGroup.options[0];

        // waiting for modal to be loaded
        await wrapper.vm.$nextTick();

        const modal = wrapper.find('.sw-modal');

        // clear color value
        modal.vm.currentOption.colorHexCode = '';

        modal.vm.onSave();

        // waiting for the modal to dissapear
        await wrapper.vm.$nextTick();

        const emptyHexCodeValue = wrapper.find('.sw-data-grid__cell--colorHexCode span').text();

        expect(emptyHexCodeValue).toBe('');
    });
});
