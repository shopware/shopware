import { shallowMount } from '@vue/test-utils';

import 'src/app/component/entity/sw-bulk-edit-modal';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/base/sw-modal';
import 'src/app/component/grid/sw-pagination';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/user-config',
    status: 200,
    response: { data: [] },
});

describe('src/app/component/entity/sw-bulk-edit-modal', () => {
    let wrapper;
    let stubs;

    const classes = {
        componentRoot: 'sw-bulk-edit-modal',
        bulkEditListHeader: 'sw-data-grid__header',
        bulkEditList: 'sw-data-grid',
        bulkEditListCellContent: 'sw-data-grid__cell-content',
    };

    const modal = async () => {
        return shallowMount(await Shopware.Component.build('sw-bulk-edit-modal'), {
            stubs: stubs,
            data() {
                return {};
            },
            propsData: {
                selection: {
                    uuid1: { id: 'uuid1', manufacturer: 'Wordify', name: 'Portia Jobson' },
                    uuid2: { id: 'uuid2', manufacturer: 'Twitternation', name: 'Baxy Eardley' },
                    uuid3: { id: 'uuid3', manufacturer: 'Skidoo', name: 'Arturo Staker' },
                },
                bulkGridEditColumns: [],
                currencies: [],
            },
            provide: {
                shortcutService: {
                    startEventListener: () => {
                    },
                    stopEventListener: () => {
                    },
                },
            },
        });
    };

    beforeAll(async () => {
        stubs = {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-pagination': await Shopware.Component.build('sw-pagination'),
            'sw-checkbox-field': {
                template: '<div class="sw-checkbox-field"></div>',
            },
            'sw-icon': true,
            'sw-button': true,
            'sw-select-field': true,
        };
    });

    it('has the correct class', async () => {
        wrapper = await modal();

        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it('emits modal-close if modal is closed', async () => {
        wrapper = await modal();

        const wrapperModal = wrapper.findComponent(stubs['sw-modal']);

        wrapperModal.vm.$emit('modal-close');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should have a pagination', async () => {
        wrapper = await modal();

        const pagination = wrapper.find('.sw-pagination');

        expect(pagination.exists()).toBe(true);
    });

    it('should have one page initially', async () => {
        wrapper = await modal();
        await wrapper.vm.$nextTick();

        const paginationButtons = wrapper.findAll('.sw-pagination__list-button');

        expect(paginationButtons).toHaveLength(1);
    });
});
