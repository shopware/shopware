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
    response: { data: [] }
});

describe('src/app/component/entity/sw-bulk-edit-modal', () => {
    let wrapper;

    const classes = {
        componentRoot: 'sw-bulk-edit-modal',
        bulkEditListHeader: 'sw-data-grid__header',
        bulkEditList: 'sw-data-grid',
        bulkEditListCellContent: 'sw-data-grid__cell-content'
    };

    const stubs = {
        'sw-modal': Shopware.Component.build('sw-modal'),
        'sw-data-grid': Shopware.Component.build('sw-data-grid'),
        'sw-pagination': Shopware.Component.build('sw-pagination'),
        'sw-checkbox-field': {
            template: '<div class="sw-checkbox-field"></div>'
        },
        'sw-icon': true,
        'sw-button': true,
        'sw-field': true
    };

    const modal = () => {
        return shallowMount(Shopware.Component.build('sw-bulk-edit-modal'), {
            stubs: stubs,
            data() {
                return {
                };
            },
            propsData: {
                selection: {
                    uuid1: { id: 'uuid1', manufacturer: 'Wordify', name: 'Portia Jobson' },
                    uuid2: { id: 'uuid2', manufacturer: 'Twitternation', name: 'Baxy Eardley' },
                    uuid3: { id: 'uuid3', manufacturer: 'Skidoo', name: 'Arturo Staker' }
                },
                bulkGridEditColumns: [],
                currencies: []
            },
            provide: {
                shortcutService: {
                    startEventListener: () => {
                    },
                    stopEventListener: () => {
                    }
                }
            }
        });
    };

    it('has the correct class', () => {
        wrapper = modal();

        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it('emits modal-close if modal is closed', () => {
        wrapper = modal();

        const wrapperModal = wrapper.findComponent(stubs['sw-modal']);

        wrapperModal.vm.$emit('modal-close');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should have a pagination', async () => {
        wrapper = modal();

        const pagination = wrapper.find('.sw-pagination');

        expect(pagination.exists()).toBe(true);
    });

    it('should have one page initially', async () => {
        wrapper = modal();
        await wrapper.vm.$nextTick();

        const paginationButtons = wrapper.findAll('.sw-pagination__list-button');

        expect(paginationButtons.length).toBe(1);
    });
});
