/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

describe('module/sw-import-export/components/sw-import-export-activity-result-modal', () => {
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


    async function createWrapper(logEntity = getLogEntityMock()) {
        return mount(await wrapTestComponent('sw-import-export-activity-result-modal', { sync: true }), {
            props: {
                logEntity,
            },
            global: {
                provide: {
                    importExport: {},
                },
                stubs: {
                    'sw-card': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-color-badge': true,
                    'sw-button': true,
                    'sw-grid': true,
                },
            },
        });
    }

    it.each([
        ['Profile name', 'Default product', 'profile'],
        ['File name', 'Default product_20211108-141453.csv', 'file-name'],
        ['Imported records', '1', 'imported'],
        ['Date / time', '8 November 2021 at 14:50', 'date'],
        ['User', 'admin', 'user'],
        ['Type', 'sw-import-export.activity.detail.importLabel', 'type'],
    ])('should display %s', async (_, expectedValue, selector) => {
        const wrapper = await createWrapper();
        await flushPromises();

        const element = wrapper.find(`.sw-import-export-activity-result-modal__log-info-${selector} dd`);

        expect(element.text()).toBe(expectedValue);
    });
});
