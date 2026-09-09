/**
 * @sw-package framework
 */
import { updateSubscriber, register, handleGet } from '@shopware-ag/meteor-admin-sdk/es/data';
import get from 'lodash-es/get';
import debounce from 'lodash-es/debounce';
import cloneDeepWith from 'lodash-es/cloneDeepWith';
import { selectData } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/selectData';
import MissingPrivilegesError from '@shopware-ag/meteor-admin-sdk/es/_internals/privileges/missing-privileges-error';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import Entity from 'src/core/data/entity.data';

interface scopeInterface {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    $watch(path: string, callback: (value: any) => void, options: { deep: boolean; immediate: boolean }): () => void;
    $: {
        uid: number;
    };
}

/**
 * Everything publishing needs from whatever holds the data — an Options API instance plus a path, or
 * a ref. Paths handed to `write` are relative to the published value, so neither side has to know
 * how the other addresses it.
 *
 * @private
 */
export type PublishScope = {
    /** Identity of the publisher, so publishing the same id twice can be told apart from updating it. */
    uid: number | undefined;
    /** The value being published. */
    read: () => unknown;
    /** Replaces the published value, or the property inside it that `segments` addresses. */
    write: (segments: string[], value: unknown) => void;
    /** Reports every deep change, once immediately. Returns the function that stops watching. */
    watch: (callback: (value: unknown) => void) => () => void;
    /** Registers the cleanup that stops publishing when the publisher goes away. */
    onTeardown: (teardown: () => void) => void;
};
interface publishOptions {
    id: string;
    path: string;
    scope: scopeInterface;
    deprecated?: boolean;
    deprecationMessage?: string;
    showDoubleRegistrationError?: boolean;
}

/** @private */
export type publishScopedOptions = Omit<publishOptions, 'id' | 'path' | 'scope'>;

type dataset = {
    id: string;
    scope: number;
    data: unknown;
    deprecated?: boolean;
    deprecationMessage?: string;
};

type transferObject = {
    [key: string | symbol]: unknown;
};

type ParsedPath = {
    pathToLastSegment: string;
    lastSegment: string;
};

// This is used by the Vue devtool extension plugin
let publishedDataSets: dataset[] = [];

/**
 * This array is used to keep track of datasets that should be unregistered
 */
let unregisterPublishDataIds: string[] = [];

/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * Deep clone with custom handling for entities and entity collections
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function deepCloneWithEntity(data: any): any {
    return cloneDeepWith(
        data,
        (value: {
            __identifier__?: () => string;
            source?: string;
            entity?: keyof EntitySchema.Entities;
            criteria?: Criteria;
            total?: number;
            aggregations?: unknown;
            id?: string;
            _entityName?: keyof EntitySchema.Entities;
            _draft?: unknown;
            _origin?: unknown;
            _isDirty?: boolean;
            _isNew?: boolean;
        }) => {
            // If value is a entity collection, we need to clone it custom
            if (
                value?.__identifier__ &&
                typeof value.__identifier__ === 'function' &&
                value.__identifier__() === 'EntityCollection'
            ) {
                return new EntityCollection(
                    value.source!,
                    value.entity!,
                    // @ts-expect-error - we don't want to provide a context
                    {},
                    value.criteria === null ? value.criteria : Criteria.fromCriteria(value.criteria!),
                    // @ts-expect-error - value is an array inside a entity collection
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                    deepCloneWithEntity(Array.from(value)),
                    value.total,
                    value.aggregations,
                );
            }

            // If value is a entity, we need to clone it custom
            if (value?.__identifier__ && typeof value.__identifier__ === 'function' && value.__identifier__() === 'Entity') {
                return new Entity(
                    value.id!,
                    value._entityName!,
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                    deepCloneWithEntity(value._draft),
                    {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        originData: deepCloneWithEntity(value._origin),
                        isDirty: value._isDirty,
                        isNew: value._isNew,
                    },
                );
            }

            return undefined;
        },
    );
}

/* eslint-enable @typescript-eslint/no-explicit-any */

handleGet((data, additionalOptions) => {
    const origin = additionalOptions?._event_?.origin;
    const registeredDataSet = publishedDataSets.find((s) => s.id === data.id);

    if (!registeredDataSet) {
        return null;
    }

    if (registeredDataSet.deprecated) {
        const extension = Object.values(Shopware.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalOptions._event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalOptions._event_.origin}" not found.`);
        }

        const debugArgs = [
            'CORE',
            `The extension "${extension.name}" uses a deprecated data set "${data.id}". ${registeredDataSet.deprecationMessage}`,
        ];
        if (process.env.NODE_ENV !== 'production') {
            Shopware.Utils.debug.error(...debugArgs);
        } else {
            Shopware.Utils.debug.warn(...debugArgs);
        }
    }

    const selectors = data.selectors;

    if (!selectors) {
        return registeredDataSet.data;
    }

    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const clonedData = deepCloneWithEntity(registeredDataSet.data);

    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    const selectedData = selectData(clonedData, selectors, 'datasetGet', origin);

    if (selectedData instanceof MissingPrivilegesError) {
        console.error(selectedData);
    }

    return selectedData;
});

/**
 * Splits an object path like "foo.bar.buz" to "{ pathToLastSegment: 'foo.bar', lastSegment: 'buz' }".
 */
function parsePath(path: string): ParsedPath | null {
    if (!path.includes('.')) {
        return null;
    }

    const properties = path.split('.');
    const lastSegment = properties.pop();
    const pathToLastSegment = properties.join('.');

    if (lastSegment && lastSegment.length && pathToLastSegment && pathToLastSegment.length) {
        return {
            pathToLastSegment,
            lastSegment,
        };
    }

    return null;
}

/**
 * Publishes whatever a `PublishScope` holds, and keeps publishing it until that scope tears down.
 * `publishData()` and `usePublishedData()` both come through here; the only difference between them
 * is how their scope reaches the data.
 *
 * @private
 */
export function publishScopedData(
    id: string,
    scope: PublishScope,
    { deprecated, deprecationMessage, showDoubleRegistrationError = true }: publishScopedOptions = {},
): () => void {
    if (unregisterPublishDataIds.includes(id)) {
        unregisterPublishDataIds = unregisterPublishDataIds.filter((value) => value !== id);
    }
    const registeredDataSet = publishedDataSets.find((s) => s.id === id);

    // Dataset registered from different scope? Prevent update.
    if (registeredDataSet && registeredDataSet.scope !== scope.uid) {
        if (showDoubleRegistrationError) {
            console.error(`The dataset id "${id}" you tried to publish is already registered.`);
        }

        return () => {};
    }

    // Dataset registered from same scope? Update.
    if (registeredDataSet && registeredDataSet.scope === scope.uid) {
        register({ id: id, data: scope.read() }).catch(() => {});

        return () => {};
    }

    // Create updateSubscriber which maps back changes from the app to Vue
    updateSubscriber(id, (value) => {
        // Null updates are not allowed
        if (!value) {
            return;
        }

        function setObject(transferObject: transferObject, prefix: string[] = []): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            if (typeof transferObject?.getIsDirty === 'function' && !transferObject.getIsDirty()) {
                return;
            }

            Object.keys(transferObject).forEach((property) => {
                const segments = [
                    ...prefix,
                    property,
                ];

                if (
                    // @ts-expect-error
                    Shopware.Utils.hasOwnProperty(transferObject[property], 'getDraft', this) &&
                    // @ts-expect-error
                    typeof transferObject[property].getDraft === 'function'
                ) {
                    setObject(
                        {
                            [property]: Shopware.Utils.object.cloneDeep(transferObject[property]),
                        },
                        segments,
                    );

                    return;
                }

                if (Array.isArray(transferObject[property])) {
                    (transferObject[property] as Array<unknown>).forEach((c, index) => {
                        setObject({ [index]: c }, segments);
                    });

                    return;
                }

                scope.write(segments, transferObject[property]);
            });
        }

        // @ts-expect-error
        if (typeof value.data?.getDraft === 'function') {
            setObject(value.data as transferObject);

            return;
        }

        if (Array.isArray(value.data)) {
            value.data.forEach((entry, index) => {
                if (entry === null || typeof entry !== 'object') {
                    return;
                }

                setObject({ [index]: entry as unknown });
            });
        } else if (typeof value.data === 'object') {
            setObject(value.data as transferObject);

            return;
        }

        scope.write([], value.data);
    });

    // Watch for changes on the reactive source and automatically publish them
    const unwatch = scope.watch(
        debounce((value: unknown) => {
            if (unregisterPublishDataIds.includes(id)) {
                unregisterPublishDataIds = unregisterPublishDataIds.filter((v) => v !== id);
                unwatch();

                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const clonedValue = deepCloneWithEntity(value);

            register({ id: id, data: clonedValue }).catch(() => {});

            const dataSet = publishedDataSets.find((set) => set.id === id);
            if (dataSet) {
                dataSet.data = value;

                return;
            }

            publishedDataSets.push({
                id,
                data: clonedValue,
                scope: scope.uid,
                deprecated,
                deprecationMessage,
            });
        }, 750),
    );

    scope.onTeardown(() => {
        publishedDataSets = publishedDataSets.filter((value) => value.id !== id);
        unregisterPublishDataIds.push(id);

        unwatch();
    });

    register({ id: id, data: scope.read() }).catch(() => {});

    // Return method to manually deregister the dataset
    return function unregisterPublishData() {
        publishedDataSets = publishedDataSets.filter((value) => value.id !== id);
        unregisterPublishDataIds.push(id);

        unwatch();
    };
}

/**
 * A `PublishScope` over an Options API instance and a path into it. `write` re-joins the relative
 * segments onto that path, so the instance is still addressed exactly as it was before the scope
 * seam existed.
 */
function instanceScope(instance: scopeInterface, path: string): PublishScope {
    return {
        uid: instance?.$?.uid,

        read: (): unknown => get(instance, path),

        write: (segments, value) => {
            const fullPath = [
                path,
                ...segments,
            ].join('.');
            const parsedPath = parsePath(fullPath);

            // A single-segment path names a member of the instance itself, so there is no container
            // to resolve first.
            if (parsedPath === null) {
                // @ts-expect-error - the path is the caller's contract, not something typed here
                instance[fullPath] = value;

                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            Shopware.Utils.object.get(instance, parsedPath.pathToLastSegment)[parsedPath.lastSegment] = value;
        },

        watch: (callback) => instance.$watch(path, callback, { deep: true, immediate: true }),

        onTeardown: (teardown) => {
            // @ts-expect-error - Defined in meteor-sdk-data.plugin.ts
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            instance.dataSetUnwatchers.push(teardown);
        },
    };
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function publishData({ id, path, scope, ...options }: publishOptions): () => void {
    return publishScopedData(id, instanceScope(scope, path), options);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function getPublishedDataSets(): dataset[] {
    return publishedDataSets;
}
