import { EMPTY_STATE, createBackend, mountActivity, recordNotifications, unmountActivity } from '../activity.harness';

/**
 * @sw-package fundamentals@after-sales
 */

type SearchedCriteria = { parse: () => Record<string, unknown> };

describe('sw-import-export-activity: loading', () => {
    afterEach(unmountActivity);

    it('loads the imports and dry runs of an importer, newest first, with their user, file, profile and invalid records', async () => {
        // ARRANGE
        const backend = createBackend([[]]);

        // ACT
        await mountActivity(backend, { type: 'import' });

        // ASSERT
        const criteria = backend.logRepository.search.mock.calls[0][0 as never] as SearchedCriteria;
        expect(criteria.parse()).toEqual({
            page: 1,
            filter: [
                {
                    type: 'multi',
                    operator: 'OR',
                    queries: [
                        { type: 'equals', field: 'activity', value: 'import' },
                        { type: 'equals', field: 'activity', value: 'dryrun' },
                    ],
                },
            ],
            sort: [{ field: 'createdAt', order: 'DESC', naturalSorting: false }],
            associations: {
                user: { 'total-count-mode': 1 },
                file: { 'total-count-mode': 1 },
                profile: { 'total-count-mode': 1 },
                invalidRecordsLog: { 'total-count-mode': 1, associations: { file: { 'total-count-mode': 1 } } },
            },
            'total-count-mode': 1,
        });
    });

    it('loads the exports of an exporter', async () => {
        // ARRANGE
        const backend = createBackend([[]]);

        // ACT
        await mountActivity(backend, { type: 'export' });

        // ASSERT
        const criteria = backend.logRepository.search.mock.calls[0][0 as never] as SearchedCriteria;
        expect(criteria.parse().filter).toEqual([{ type: 'equals', field: 'activity', value: 'export' }]);
    });

    it('shows a loader while the processes load', async () => {
        // ARRANGE
        const backend = createBackend([new Promise(() => {})]);

        // ACT
        const wrapper = await mountActivity(backend);

        // ASSERT
        expect(wrapper.find('.sw-loader').exists()).toBe(true);
        expect(wrapper.find(EMPTY_STATE).exists()).toBe(false);
    });

    it('reports a search result that is no entity collection as a loading error', async () => {
        // ARRANGE
        const notifications = recordNotifications();
        const backend = createBackend([{ total: 0 }]);

        // ACT
        const wrapper = await mountActivity(backend);

        // ASSERT
        expect(notifications).toEqual([
            { variant: 'error', message: 'global.notification.notificationLoadingDataErrorMessage' },
        ]);
        expect(wrapper.find(EMPTY_STATE).exists()).toBe(true);
    });

    it('reports a loading error without a message with the general loading error', async () => {
        // ARRANGE
        const notifications = recordNotifications();
        const backend = createBackend([Object.assign(new Error(), { message: undefined })]);

        // ACT
        await mountActivity(backend);

        // ASSERT
        expect(notifications).toEqual([
            { variant: 'error', message: 'global.notification.notificationLoadingDataErrorMessage' },
        ]);
    });
});
