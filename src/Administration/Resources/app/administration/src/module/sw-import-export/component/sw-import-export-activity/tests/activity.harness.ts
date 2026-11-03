import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent } from 'vue';
import type { PublicInstance } from 'src/core/public-component';
import type SwImportExportActivity from '../index';
import { Criteria, EntityCollection } from '../dependencies';

/**
 * @sw-package fundamentals@after-sales
 */

/**
 * One row of the activity listing, one per import or export process.
 *
 * @private
 */
export const ACTIVITY_ROW = '.sw-entity-listing__row';

/**
 * The empty state the activity shows when there is no process.
 *
 * @private
 */
export const EMPTY_STATE = '.mt-empty-state';

/** @private */
export const ACTION_SHOW_RESULTS = '.sw-import-export-activity__results-action';
/** @private */
export const ACTION_SHOW_LOG_INFO = '.sw-import-export-activity__log-info-action';
/** @private */
export const ACTION_DOWNLOAD = '.sw-import-export-activity__download-action';
/** @private */
export const ACTION_OPEN_PROFILE = '.sw-import-export-activity__open-profile-action';
/** @private */
export const ACTION_ABORT = '.sw-import-export-activity__abort-process-action';

/** @private */
export const LOG_INFO_MODAL = 'sw-import-export-activity-log-info-modal';
/** @private */
export const RESULT_MODAL = 'sw-import-export-activity-result-modal';
/** @private */
export const PROFILE_MODAL = 'sw-import-export-edit-profile-modal';

type Log = Record<string, unknown> & { id: string };

const wrappers: VueWrapper[] = [];

const EntityListingStub = defineComponent({
    name: 'sw-entity-listing',
    props: ['repository', 'dataSource', 'columns'],
    methods: {
        read(item: Record<string, unknown>, path: string): unknown {
            return path.split('.').reduce<unknown>((value, key) => (value as Record<string, unknown>)?.[key], item);
        },
    },
    template: `
        <div class="sw-entity-listing">
            <div v-for="item in dataSource" :key="item.id" class="sw-entity-listing__row">
                <div v-for="column in columns" :key="column.property" :data-column="column.property">
                    <slot :name="'column-' + column.property" :item="item">{{ read(item, column.dataIndex) }}</slot>
                </div>
                <div class="sw-entity-listing__actions"><slot name="actions" :item="item" /></div>
            </div>
        </div>`,
});

const ModalStub = (name: string, props: string[], emits: string[]) =>
    defineComponent({ name, props, emits, template: `<div class="${name}" />` });

/**
 * A log of an import or export process. The defaults are a running export of 263 bytes.
 *
 * @private
 */
export function createLog(id: string, overrides: Record<string, unknown> = {}): Log {
    return {
        id,
        activity: 'export',
        state: 'progress',
        records: 1,
        profileId: 'profileId',
        profileName: 'Default category',
        fileId: `file-${id}`,
        result: {},
        invalidRecordsLog: null,
        profile: { technicalName: 'default_category' },
        file: { id: `file-${id}`, size: 263 },
        createdAt: '2021-09-20T10:22:47.000+00:00',
        ...overrides,
    };
}

/**
 * Wraps logs in the entity collection a repository search returns.
 *
 * @private
 */
export function createLogCollection(logs: Log[]): EntityCollection<'import_export_log'> {
    return new EntityCollection(
        '/import-export-log',
        'import_export_log',
        null as never,
        new Criteria(1, 25),
        JSON.parse(JSON.stringify(logs)) as never,
    );
}

/**
 * The repositories and services behind the activity. Each search answers with the next of `searchResults`,
 * and the last one repeats.
 *
 * @private
 */
export function createBackend(searchResults: unknown[] = [[]]) {
    const results = [...searchResults];
    const nextResult = () => {
        const result = results.length > 1 ? results.shift() : results[0];
        if (result instanceof Error) {
            return Promise.reject(result);
        }

        return Promise.resolve(Array.isArray(result) ? createLogCollection(result as Log[]) : result);
    };

    return {
        logRepository: { search: jest.fn(nextResult) },
        profileRepository: {
            get: jest.fn((id: string) => Promise.resolve({ id, label: 'Default category' })),
            save: jest.fn(() => Promise.resolve()),
        },
        importExport: {
            cancel: jest.fn(() => Promise.resolve()),
            getDownloadUrl: jest.fn((fileId: string) => Promise.resolve(`https://shop.test/download/${fileId}`)),
        },
    };
}

/** @private */
export type Backend = ReturnType<typeof createBackend>;

/**
 * Mounts the activity against `backend`, as the import or export view shows it. Its `vm` has the public members
 * only, for the public-api spec.
 *
 * @private
 */
export async function mountActivity(
    backend: Backend,
    props: { type?: 'import' | 'export' } = {},
): Promise<VueWrapper<PublicInstance<typeof SwImportExportActivity>>> {
    const wrapper = mount(await wrapTestComponent('sw-import-export-activity', { sync: true }), {
        global: {
            stubs: {
                'sw-entity-listing': EntityListingStub,
                'sw-context-menu-item': {
                    props: ['disabled', 'variant'],
                    template: '<button :disabled="disabled"><slot /></button>',
                },
                'sw-help-text': true,
                'sw-label': { template: '<span class="sw-label"><slot /></span>' },
                'mt-link': { template: '<a class="mt-link"><slot /></a>' },
                'sw-time-ago': { props: ['date'], template: '<span class="sw-time-ago">{{ date }}</span>' },
                'sw-color-badge': true,
                'sw-loader': { template: '<div class="sw-loader" />' },
                'mt-empty-state': {
                    props: ['headline', 'description'],
                    template: '<div class="mt-empty-state">{{ headline }} {{ description }}</div>',
                },
                [LOG_INFO_MODAL]: ModalStub(LOG_INFO_MODAL, ['logEntity'], ['log-close']),
                [RESULT_MODAL]: ModalStub(RESULT_MODAL, ['logEntity', 'result'], ['result-close']),
                [PROFILE_MODAL]: ModalStub(PROFILE_MODAL, ['profile'], ['profile-save', 'profile-close']),
            },
            provide: {
                feature: {},
                importExport: backend.importExport,
                repositoryFactory: {
                    create: (entityName: string) =>
                        entityName === 'import_export_log' ? backend.logRepository : backend.profileRepository,
                },
            },
        },
        props,
    });
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper as never;
}

/**
 * Lets the stubbed modal `modal` emit `event`, as the real one does when the merchant closes or saves it.
 *
 * @private
 */
export async function emitFromModal(wrapper: VueWrapper, modal: string, event: string): Promise<void> {
    (wrapper.findComponent({ name: modal }).vm as { $emit: (name: string) => void }).$emit(event);
    await flushPromises();
}

/**
 * Returns the text of one column in every row, in order, such as `state` or `file.size`.
 *
 * @private
 */
export function getColumn(wrapper: VueWrapper, property: string): string[] {
    return wrapper.findAll(`${ACTIVITY_ROW} [data-column="${property}"]`).map((cell) => cell.text());
}

/**
 * Clicks an action in the context menu of the row at this position; the first is 0.
 *
 * @private
 */
export async function clickRowAction(wrapper: VueWrapper, position: number, action: string): Promise<void> {
    await wrapper.findAll(ACTIVITY_ROW)[position].find(action).trigger('click');
    await flushPromises();
}

/**
 * Lets the activity reload its running processes, which it does every ten seconds.
 * Call `useFakeTimers()` before mounting.
 *
 * @private
 */
export async function waitForReload(): Promise<void> {
    jest.advanceTimersByTime(10000);
    await flushPromises();
}

/**
 * Runs timers on a fake clock, so a test decides when the activity reloads.
 *
 * @private
 */
export function useFakeTimers(): void {
    jest.useFakeTimers();
}

/**
 * Records the notifications the activity shows, as `{ variant, message }`.
 *
 * @private
 */
export function recordNotifications(): Array<{ variant: string; message: unknown }> {
    const notifications: Array<{ variant: string; message: unknown }> = [];
    jest.spyOn(Shopware.Store.get('notification'), 'createNotification').mockImplementation((notification) => {
        notifications.push({ variant: notification.variant as string, message: notification.message });

        return null;
    });

    return notifications;
}

/**
 * Unmounts every activity the tests mounted and restores timers and spies.
 *
 * @private
 */
export function unmountActivity(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    jest.useRealTimers();
    jest.restoreAllMocks();
}
