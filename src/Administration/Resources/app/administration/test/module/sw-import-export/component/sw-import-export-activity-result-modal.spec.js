import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-activity-result-modal';

describe('module/sw-import-export/components/sw-import-export-activity-result-modal', () => {
    let wrapper;

    function getLogEntityMock() {
        return {
            state: 'succeeded',
            activity: 'import',
            records: 1,
            username: 'admin',
            profileName: 'Default product',
            config: {},
            result: {
                product: {
                    insert: 1,
                    update: 0,
                    insertSkip: 0,
                    updateSkip: 0,
                    insertError: 0,
                    updateError: 0,
                },
            },
            createdAt: '2021-11-08T14:50:53.684+00:00',
            user: {
                username: 'admin',
            },
            profile: {
                label: 'Default product',
                sourceEntity: 'product',
            },
            file: {
                originalName: 'Default product_20211108-141453.csv',
                size: 476,
            },
        };
    }


    function createWrapper(logEntity = getLogEntityMock()) {
        return shallowMount(Shopware.Component.build('sw-import-export-activity-result-modal'), {
            propsData: {
                logEntity
            },
            provide: {
                importExport: {}
            },
            stubs: {
                'sw-modal': {
                    template: '<div><slot></slot></div>'
                },
                'sw-card': {
                    template: '<div><slot></slot></div>'
                },
                'sw-color-badge': true,
                'sw-button': true,
                'sw-grid': true,
            }
        });
    }

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
            wrapper = null;
        }
    });

    it('should be a vue.js component', () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        ['Profile name', 'Default product', 'profile'],
        ['File name', 'Default product_20211108-141453.csv', 'file-name'],
        ['Imported records', '1', 'imported'],
        ['Date / time', 'November 8, 2021, 02:50 PM', 'date'],
        ['User', 'admin', 'user'],
        ['Type', 'sw-import-export.activity.detail.importLabel', 'type'],
    ])('should display %s', (_, expectedValue, selector) => {
        wrapper = createWrapper();
        const element = wrapper.find(`.sw-import-export-activity-result-modal__log-info-${selector} dd`);

        expect(element.text()).toBe(expectedValue);
    });
});
