/**
 * @package services-settings
 */
import { shallowMount } from '@vue/test-utils_v2';
import swImportExportActivityLogInfoModal from 'src/module/sw-import-export/component/sw-import-export-activity-log-info-modal';

Shopware.Component.register('sw-import-export-activity-log-info-modal', swImportExportActivityLogInfoModal);

describe('module/sw-import-export/components/sw-import-export-activity-log-info-modal', () => {
    /** @type Wrapper */
    let wrapper;

    function getLogEntityMock() {
        return {
            activity: 'export',
            state: 'succeeded',
            records: 1,
            username: 'admin',
            createdAt: '2021-11-05T09:08:40.015+00:00',
            profile: {
                label: 'Default product',
            },
            file: {
                originalName: 'star-lord.csv',
                size: 458,
            },
        };
    }

    async function createWrapper(logEntity = getLogEntityMock()) {
        return shallowMount(await Shopware.Component.build('sw-import-export-activity-log-info-modal'), {
            provide: {
                importExport: {},
            },
            stubs: {
                'sw-modal': {
                    template: `
                    <div class="sw-modal-stub">
                        <slot></slot>
                    </div>`,
                },
                'sw-button': true,
                'sw-color-badge': true,
            },
            propsData: {
                logEntity,
            },
        });
    }

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
            wrapper = null;
        }
    });

    it('should be a vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        ['file name', '.sw-import-export-activity-log-info-modal__item-file-name dd', 'star-lord.csv'],
        ['profile name', '.sw-import-export-activity-log-info-modal__item-profile dd', 'Default product'],
        ['updated records', '.sw-import-export-activity-log-info-modal__item-records dd', '1'],
        ['file size', '.sw-import-export-activity-log-info-modal__item-size dd', '458.00B'],
        ['date', '.sw-import-export-activity-log-info-modal__item-date dd', '5 November 2021 at 09:08'],
        ['user', '.sw-import-export-activity-log-info-modal__item-user dd', 'admin'],
    ])('should display the %s', async (_, selector, expectedText) => {
        wrapper = await createWrapper();

        const text = wrapper.find(selector).text();
        expect(text).toBe(expectedText);
    });

    it.each([
        ['error', 'failed'],
        ['success', 'succeeded'],
        [undefined, 'pending'],
    ])('should display badge as %s', async (expectedVariant, logEntityState) => {
        const logEntity = getLogEntityMock();
        logEntity.state = logEntityState;

        wrapper = await createWrapper(logEntity);

        const colorBadge = wrapper.find('sw-color-badge-stub');
        expect(colorBadge.attributes('variant')).toBe(expectedVariant);
    });

    it('should disable the button when export has not been finished yet', async () => {
        const logEntity = getLogEntityMock();
        logEntity.activity = 'export';
        logEntity.state = 'pending';

        wrapper = await createWrapper(logEntity);

        const downloadButton = wrapper.find('sw-button-stub');
        expect(downloadButton.attributes('disabled')).toBe('true');
    });

    it('should enable the button when export has been finished', async () => {
        const logEntity = getLogEntityMock();
        logEntity.activity = 'export';
        logEntity.state = 'succeeded';

        wrapper = await createWrapper(logEntity);

        const downloadButton = wrapper.find('sw-button-stub');
        expect(downloadButton.attributes('disabled')).toBeUndefined();
    });
});
