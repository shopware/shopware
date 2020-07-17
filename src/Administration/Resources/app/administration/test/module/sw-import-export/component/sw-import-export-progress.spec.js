import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-progress';

describe('module/sw-import-export/components/sw-import-export-progress', () => {
    let wrapper;
    let localVue;

    beforeEach(() => {
        localVue = createLocalVue();

        wrapper = shallowMount(Shopware.Component.build('sw-import-export-progress'), {
            localVue,
            stubs: [
                'sw-progress-bar', 'sw-button', 'sw-import-export-activity-detail-modal'
            ],
            mocks: {
                $tc: (translationPath) => translationPath
            },
            provide: {
                importExport: { getDownloadUrl: () => { return ''; } }
            }
        });
    });

    afterEach(() => {
        localVue = null;
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('progress bar should not be shown when total is null', () => {
        const progressBar = wrapper.find('.sw-import-export-progress__progress-bar-bar');

        expect(progressBar.exists()).toBeFalsy();
    });

    it('progress bar should be shown when total value is set', () => {
        wrapper.setProps({
            total: 5
        });

        const progressBar = wrapper.find('.sw-import-export-progress__progress-bar-bar');

        expect(progressBar.exists()).toBeTruthy();
        expect(progressBar.isVisible()).toBeTruthy();
    });

    it('progress should be 50 percent', () => {
        wrapper.setProps({
            offset: 5,
            total: 10
        });

        expect(wrapper.vm.percentageProgress).toEqual(50);
    });

    it('failed import stats should be visible when log entry is set', () => {
        wrapper.setProps({
            activityType: 'import',
            state: 'failed',
            logEntry: {
                records: 10,
                invalidRecordsLog: {
                    records: 10,
                    file: {
                        id: 'id',
                        accessToken: 'accessToken'
                    }
                }
            }
        });

        const stats = wrapper.find('.sw-import-export-progress__stats-list-successful');

        expect(stats.exists()).toBeTruthy();
        expect(stats.isVisible()).toBeTruthy();
    });

    it('success import stats should be visible when log entry is set', () => {
        wrapper.setProps({
            activityType: 'import',
            state: 'succeeded',
            logEntry: {
                records: 10
            }
        });

        const stats = wrapper.find('.sw-import-export-progress__stats-list-success');

        expect(stats.exists()).toBeTruthy();
        expect(stats.isVisible()).toBeTruthy();
    });

    it('failed export stats should be visible when log entry is set', () => {
        wrapper.setProps({
            activityType: 'export',
            state: 'failed',
            logEntry: {
                records: 10,
                file: {
                    id: 'id',
                    accessToken: 'accessToken'
                }
            }
        });

        const stats = wrapper.find('.sw-import-export-progress__stats-list-failure');

        expect(stats.exists()).toBeTruthy();
        expect(stats.isVisible()).toBeTruthy();
    });

    it('finished class should be set on progress bar when progress finished', () => {
        wrapper.setProps({
            offset: 10,
            total: 10,
            state: 'succeeded'
        });

        expect(wrapper.vm.progressBarClasses).toEqual({ 'is--errored': false, 'is--finished': true });
    });

    it('error class should be set on progress bar when state failed', () => {
        wrapper.setProps({
            offset: 10,
            total: 10,
            state: 'failed'
        });

        expect(wrapper.vm.progressBarClasses).toEqual({ 'is--errored': true, 'is--finished': false });
    });
});
