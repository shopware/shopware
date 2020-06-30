import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-detail';
import 'src/module/sw-settings-custom-field/component/sw-custom-field-list';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/grid/sw-pagination';

const customFields = [];

for (let i = 0; i < 11; i += 1) {
    const customFieldPosition = i;

    const customField = {
        id: `${i}7ef331cff2f494a9271479385ace711`,
        name: 'custom_beauty_consectetur_aut_ut',
        config: {
            label: { 'en-GB': 'consectetur aut ut' },
            customFieldType: 'checkbox',
            customFieldPosition: customFieldPosition
        }
    };

    customFields.push(customField);
}

const set = {
    id: '9f359a2ab0824784a608fc2a443c5904',
    customFields: customFields
};


function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-settings-custom-field-set-detail'), {
        mocks: {
            $tc: () => {},
            $route: {
                params: {
                    id: '1234'
                }
            },
            $device: {
                getSystemKey: () => {},
                onResize: () => {}
            }
        },
        provide: {
            repositoryFactory: {
                create() {
                    return {
                        get() {
                            set.customFields.sort((first, second) => {
                                const positionOfFirst = first.config.customFieldPosition;
                                const positionOfSecond = second.config.customFieldPosition;

                                return positionOfFirst > positionOfSecond ? 1 : -1;
                            });

                            return Promise.resolve(set);
                        },
                        save: () => Promise.resolve(),
                        search: () => {
                            const response = set.customFields;
                            response.total = set.customFields.length;

                            return Promise.resolve(response);
                        },
                        searchIds: () => {
                            const total = set.customFields.length;

                            return Promise.resolve({ total });
                        }
                    };
                }
            }
        },
        stubs: {
            'sw-page': '<div><slot name="content"></slot></div>',
            'sw-card-view': '<div><slot></slot></div>',
            'sw-custom-field-set-detail-base': '<div></div>',
            'sw-button': '<button></button>',
            'sw-custom-field-list': Shopware.Component.build('sw-custom-field-list'),
            'sw-card': '<div><slot></slot></div>',
            'sw-empty-state': '<div></div>',
            'sw-simple-search-field': '<div></div>',
            'sw-container': '<div><slot></slot></div>',
            'sw-grid': Shopware.Component.build('sw-grid'),
            'sw-context-button': '<div></div>',
            'sw-grid-column': '<div class="sw-grid-column"><slot></slot></div>',
            'sw-grid-row': '<div class="sw-grid-row"><slot></slot></div>',
            'sw-field': '<div></div>',
            'sw-pagination': Shopware.Component.build('sw-pagination'),
            'sw-icon': '<div></div>'
        }
    });
}

describe('src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-detail', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should create a new custom field', () => {
        const amountOfCustomFields = wrapper.findAll('.sw-grid-row').length;

        expect(amountOfCustomFields).toBe(11);

        const newCustomField =
        {
            name: 'custom_beauty_eee',
            id: '435652ab554626ab88f01bed95e678',
            config: {
                label: { 'en-GB': null },
                customFieldType: 'media',
                customFieldPosition: 3
            }
        };
        const allCustomFields = wrapper.vm.set.customFields;
        allCustomFields.push(newCustomField);

        const newAmountOfCustomFields = wrapper.findAll('.sw-grid-row').length;
        expect(newAmountOfCustomFields).toBe(12);

        // get rid of newly added custom field
        wrapper.vm.set.customFields = wrapper.vm.set.customFields.reduce((accumulator, currentField) => {
            if (currentField.id === newCustomField.id) {
                return accumulator;
            }

            accumulator.push(currentField);

            return accumulator;
        }, []);
    });

    it('should sort custom fields by position ', () => {
        const customFieldPositionCells = wrapper.findAll('.sw-grid-column[dataIndex="position"]').wrappers;
        const [first, second, third, fourth] = customFieldPositionCells;

        expect(first.text()).toBe('0');
        expect(second.text()).toBe('1');
        expect(third.text()).toBe('2');
        expect(fourth.text()).toBe('3');
    });

    it('should sort custom fields after editing and saving', () => {
        const [firstCustomField] = wrapper.vm.set.customFields;

        firstCustomField.config.customFieldPosition = 2;

        wrapper.vm.loadEntityData();

        const customFieldPositionCells = wrapper.findAll('.sw-grid-column[dataIndex="position"]').wrappers;
        const [first, second, third, fourth] = customFieldPositionCells;

        expect(first.text()).toBe('1');
        expect(second.text()).toBe('2');
        expect(third.text()).toBe('2');
        expect(fourth.text()).toBe('3');
    });

    it('should have a pagination', () => {
        const pagination = wrapper.find('.sw-pagination');

        expect(pagination.exists()).toBe(true);
    });

    it('should have two pages', () => {
        const paginationButtons = wrapper.findAll('.sw-pagination__list-button');

        expect(paginationButtons.length).toBe(2);
    });
});
