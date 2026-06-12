/**
 * @sw-package framework
 */

import type {
    SwMeteorEntityDataTableColumn,
    SwMeteorEntityDataTableColumnNormalization,
    SwMeteorEntityDataTableColumnRenderer,
    SwMeteorEntityDataTableColumnSortMetadata,
    SwMeteorEntityDataTableNormalizedColumn,
} from './sw-meteor-entity-data-table.types';

const componentName = 'sw-meteor-entity-data-table';
const unsupportedColumnFieldsUpstreamDependency =
    'upstream mt-data-table support for per-column cell slots or custom renderers';
const columnPositionStep = 100;

const supportedColumnFields = [
    'property',
    'dataIndex',
    'label',
    'renderer',
    'rendererOptions',
    'position',
    'sortable',
    'allowResize',
    'visible',
    'width',
    'naturalSorting',
    'useCustomSort',
    'clickable',
] as const;

const supportedRenderers: SwMeteorEntityDataTableColumnRenderer[] = [
    'text',
    'number',
    'price',
    'badge',
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function normalizeSwMeteorEntityDataTableColumns(
    configuredColumns: unknown,
): SwMeteorEntityDataTableColumnNormalization {
    if (!Array.isArray(configuredColumns)) {
        return {
            columns: [],
            sortMetadataByProperty: {},
        };
    }

    return configuredColumns.reduce<SwMeteorEntityDataTableColumnNormalization>(
        (normalization, configuredColumn, index) => {
            const { column, sortMetadata } = normalizeColumn(configuredColumn, index);

            normalization.columns.push(column);
            normalization.sortMetadataByProperty[column.property] = sortMetadata;

            return normalization;
        },
        {
            columns: [],
            sortMetadataByProperty: {},
        },
    );
}

function normalizeColumn(
    configuredColumn: unknown,
    index: number,
): {
    column: SwMeteorEntityDataTableNormalizedColumn;
    sortMetadata: SwMeteorEntityDataTableColumnSortMetadata;
} {
    if (!isRecord(configuredColumn)) {
        throw new Error(`[${componentName}] Column at index ${index} must be an object.`);
    }

    assertNoUnsupportedFields(configuredColumn, index);

    const property = readRequiredString(configuredColumn, 'property', index);
    const dataIndex = readOptionalString(configuredColumn.dataIndex) ?? property;
    const label = translateLabel(readRequiredString(configuredColumn, 'label', index));
    const renderer = normalizeRenderer(configuredColumn.renderer, property);
    const position = normalizePosition(configuredColumn.position, index, property);
    const width = normalizeWidth(configuredColumn.width, property);
    const sourceColumn = {
        ...(configuredColumn as SwMeteorEntityDataTableColumn),
        dataIndex,
        renderer,
        position,
    };

    const normalizedColumn: SwMeteorEntityDataTableNormalizedColumn = {
        label,
        property,
        renderer,
        position,
    };

    copyOptionalField(configuredColumn, normalizedColumn, 'rendererOptions');
    copyOptionalBoolean(configuredColumn, normalizedColumn, 'sortable');
    copyOptionalBoolean(configuredColumn, normalizedColumn, 'allowResize');
    copyOptionalBoolean(configuredColumn, normalizedColumn, 'visible');
    copyOptionalBoolean(configuredColumn, normalizedColumn, 'clickable');

    if (typeof width === 'number') {
        normalizedColumn.width = width;
    }

    return {
        column: normalizedColumn,
        sortMetadata: {
            property,
            dataIndex,
            naturalSorting: configuredColumn.naturalSorting === true,
            useCustomSort: configuredColumn.useCustomSort === true,
            sourceColumn,
        },
    };
}

function assertNoUnsupportedFields(column: Record<string, unknown>, index: number): void {
    const unsupportedFields = Object.keys(column).filter((field) => {
        return !supportedColumnFields.includes(field as (typeof supportedColumnFields)[number]);
    });

    if (unsupportedFields.length <= 0) {
        return;
    }

    throw new Error(
        `[${componentName}] Column ${describeColumn(column, index)} contains unsupported field(s): ${unsupportedFields.join(
            ', ',
        )}. These legacy sw-data-grid fields require ${unsupportedColumnFieldsUpstreamDependency} before they can be migrated.`,
    );
}

function readRequiredString(column: Record<string, unknown>, field: 'property' | 'label', index: number): string {
    const value = readOptionalString(column[field]);

    if (value) {
        return value;
    }

    throw new Error(`[${componentName}] Please specify a "${field}" to render a column at index ${index}.`);
}

function readOptionalString(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const trimmedValue = value.trim();

    return trimmedValue.length > 0 ? trimmedValue : null;
}

function normalizeRenderer(value: unknown, property: string): SwMeteorEntityDataTableColumnRenderer {
    if (value === undefined || value === null) {
        return 'text';
    }

    if (isSupportedRenderer(value)) {
        return value;
    }

    throw new Error(
        `[${componentName}] Column "${property}" uses unsupported renderer "${stringifyValue(
            value,
        )}". Supported mt-data-table renderers are: ${supportedRenderers.join(', ')}.`,
    );
}

function isSupportedRenderer(value: unknown): value is SwMeteorEntityDataTableColumnRenderer {
    return typeof value === 'string' && supportedRenderers.includes(value as SwMeteorEntityDataTableColumnRenderer);
}

function normalizePosition(value: unknown, index: number, property: string): number {
    if (value === undefined || value === null) {
        return index * columnPositionStep;
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    throw new Error(`[${componentName}] Column "${property}" position must be a number.`);
}

function normalizeWidth(value: unknown, property: string): number | undefined {
    if (value === undefined || value === null) {
        return undefined;
    }

    if (typeof value === 'number') {
        if (Number.isFinite(value)) {
            return value;
        }

        throw new Error(`[${componentName}] Column "${property}" width must be a finite number.`);
    }

    if (typeof value !== 'string') {
        throw new Error(`[${componentName}] Column "${property}" width must be a number or string.`);
    }

    const trimmedValue = value.trim();

    if (trimmedValue === '' || trimmedValue === 'auto') {
        return undefined;
    }

    const numericWidth = trimmedValue.match(/^(\d+(?:\.\d+)?)$/u);
    const pixelWidth = trimmedValue.match(/^(\d+(?:\.\d+)?)px$/iu);
    const matchedWidth = numericWidth?.[1] ?? pixelWidth?.[1];

    if (matchedWidth) {
        return Number(matchedWidth);
    }

    throw new Error(
        `[${componentName}] Column "${property}" has unsupported width "${value}". mt-data-table only accepts numeric pixel widths or auto width.`,
    );
}

type OptionalBooleanColumnField = 'sortable' | 'allowResize' | 'visible' | 'clickable';

function copyOptionalBoolean(
    source: Record<string, unknown>,
    target: SwMeteorEntityDataTableNormalizedColumn,
    field: OptionalBooleanColumnField,
): void {
    if (typeof source[field] === 'boolean') {
        target[field] = source[field];
    }
}

function copyOptionalField<TKey extends 'rendererOptions'>(
    source: Record<string, unknown>,
    target: Partial<Record<TKey, unknown>>,
    field: TKey,
): void {
    if (Object.prototype.hasOwnProperty.call(source, field)) {
        target[field] = source[field];
    }
}

function translateLabel(label: string): string {
    const snippetService = Shopware.Snippet as { tc?: (key: string) => string } | null;
    const translatedLabel = snippetService?.tc?.(label);

    if (typeof translatedLabel === 'string' && translatedLabel.length > 0) {
        return translatedLabel;
    }

    return label;
}

function describeColumn(column: Record<string, unknown>, index: number): string {
    const property = readOptionalString(column.property);

    if (property) {
        return `"${property}"`;
    }

    return `at index ${index}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function stringifyValue(value: unknown): string {
    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean' || typeof value === 'bigint' || typeof value === 'symbol') {
        return value.toString();
    }

    if (value === null) {
        return 'null';
    }

    if (value === undefined) {
        return 'undefined';
    }

    return JSON.stringify(value) ?? '[object Object]';
}
