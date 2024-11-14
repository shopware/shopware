/**
 * @package admin
 *
 * @module core/helper/jsonapi-parser
 */
import types from 'src/core/service/utils/types.utils';
import { hasOwnProperty } from 'src/core/service/utils/object.utils';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type Item = {
    id?: unknown;
    type?: unknown;
    links?: unknown;
    meta?: unknown;
    attributes?: Record<string, unknown>;
    relationships?: Record<
        string,
        {
            data?: Item | Item[];
            links?: {
                related: string;
            };
        }
    >;
    associationLinks?: Record<string, unknown>;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type Data = {
    parsed?: true;

    errors: unknown;
    data: Item[] | Item | null;
    links: unknown;
    associations: object | null;
    aggregations: unknown;
    meta: Record<string, unknown> | null;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type Json = {
    parsed?: boolean;

    errors?: unknown;
    data?: Item[];
    links?: unknown;
    aggregations?: unknown;
    meta?: Record<string, unknown>;
    included?: Item[];
};

/**
 * Converts a JSONApi compliant data structure into a nested object structure which suits the data entry management
 * of the application much better.
 *
 * @example
 * import jsonApiParserService from 'src/core/service/jsonapi-parser.service';
 *
 * httpClient.get('/foo').then((response) => {
 *     const parsedResponse = jsonApiParserService(response.data);
 * });
 *
 * @param data Data structure
 * @returns Parsed data structure or `null` if the `data` parameter isn't an object or string.
 * @method jsonApiParserService
 * @memberOf module:core/helper/jsonapi-parser
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function jsonApiParserService(data: string | object): Data | Json | null {
    const json = convertRawDataToJson(data);

    if (!json) {
        return null;
    }

    // Provided data was parsed before or doesn't follows the JSONApi spec, so we're returning data structure untouched
    if (json.parsed === true || !areTopMemberPropertiesPresent(json)) {
        return json as Data | Json;
    }

    const convertedStructure = parseDataStructure(json);

    // Mark the converted structure as "parsed", so we're not parsing it again
    convertedStructure.parsed = true;

    return convertedStructure;
}

/**
 * Tries to convert the raw data input into a JSON format.
 *
 * @param data Data structure to parse
 * @returns Converted data structure or false when the data type wasn't an object or string.
 */
function convertRawDataToJson(data: string | object): false | Json {
    let jsonData;
    if (types.isString(data)) {
        try {
            jsonData = JSON.parse(data) as Json;
        } catch (err) {
            return false;
        }
    } else if (types.isObject(data) && !types.isArray(data)) {
        jsonData = data;
    } else {
        return false;
    }

    return jsonData;
}

/**
 * Basic check if we're dealing with a data structure which follows the JSONApi spec.
 */
function areTopMemberPropertiesPresent(json: Json): boolean {
    return json.data !== undefined || json.errors !== undefined || json.links !== undefined || json.meta !== undefined;
}

/**
 * Iterates over the `included` property of the JSONApi spec and creates a new map with unique identifiers.
 */
function createIncludeMap(included?: Item[]): Map<string, Item> {
    const includedMap = new Map<string, Item>();

    if (!included || !included.length) {
        return includedMap;
    }

    included.forEach((item) => {
        const key = `${String(item.type)}-${String(item.id)}`;
        includedMap.set(key, item);
    });

    return includedMap;
}

/**
 * Parses the data structure and converts it from JSONApi spec to a nested object structure to work with our data
 * management and handling.
 *
 * @returns parsed data structure
 */
function parseDataStructure(json: Json): Data {
    const data: Data = {
        links: null,
        errors: null,
        data: null,
        associations: null,
        aggregations: null,
        meta: null,
    };

    // Errors will be returned right away, we don't need to convert anything
    if (json.errors) {
        data.errors = json.errors;
        return data;
    }

    const includedMap = createIncludeMap(json.included);

    if (types.isArray(json.data)) {
        data.data = json.data.map((record) => {
            const dataItem = createItem(record, includedMap);

            if (hasOwnProperty(dataItem, 'associationLinks')) {
                data.associations = {
                    ...data.associations,
                    ...dataItem.associationLinks,
                };
                delete dataItem.associationLinks;
            }

            return dataItem as Remove<typeof dataItem, 'associationLinks'>;
        });
    } else if (types.isObject(json.data)) {
        const dataItem = createItem(json.data, includedMap);

        if (hasOwnProperty(dataItem, 'associationLinks')) {
            data.associations = {
                ...data.associations,
                ...dataItem.associationLinks,
            };
            delete dataItem.associationLinks;
        }
        data.data = dataItem;
    } else {
        data.data = null;
    }

    if (json.meta && Object.keys(json.meta).length) {
        data.meta = renameObjectPropertiesToCamelCase(json.meta);
    }

    if (json.links && Object.keys(json.links).length) {
        data.links = json.links;
    }

    if (json.aggregations && Object.keys(json.aggregations).length) {
        data.aggregations = json.aggregations;
    }

    return data;
}

/**
 * Creates a new object item for the nested object structure.
 */
function createItem(record: Item, includedMap: Map<string, Item>): Require<Item, 'links' | 'meta'> {
    let item = {
        id: record.id,
        type: record.type,
        links: record.links || {},
        meta: record.meta || {},
    };

    if (record.attributes && Object.keys(record.attributes).length > 0) {
        const attributes = renameObjectPropertiesToCamelCase(record.attributes);
        item = { ...item, ...attributes };
    }

    if (record.relationships) {
        const relations = createRelationships(record.relationships, includedMap);
        item = {
            ...item,
            ...relations.mappedRelations,
            ...{ associationLinks: relations.associationLinks },
        };
    }

    return item;
}

/**
 * Renames the attributes which can be kebab-case to camel-case.
 */
function renameObjectPropertiesToCamelCase(attributesCollection: Record<string, unknown>): Record<string, unknown> {
    const attributes: Record<string, unknown> = {};
    Object.keys(attributesCollection).forEach((attributeKey) => {
        const attribute = attributesCollection[attributeKey];
        const key = String(attributeKey).replace(/-([a-z])/g, (_, item: string) => item.toUpperCase());
        attributes[key] = attribute;
    });

    return attributes;
}

/**
 * Maps the included entries and creates new items out of it including dependency resolving
 */
function mapIncluded(item: Item, includedMap: Map<string, Item>): Item {
    const includedKey = `${String(item.type)}-${String(item.id)}`;
    if (!includedMap.has(includedKey)) {
        return item;
    }

    const included = includedMap.get(includedKey)!;
    return createItem(included, includedMap);
}

/**
 * Resolve the dependencies between entries in `includedMap` and the relations of the item.
 */
function createRelationships(relationships: Exclude<Item['relationships'], undefined>, includedMap: Map<string, Item>) {
    const mappedRelations: Record<string, Item | Item[] | null> = {};
    const associationLinks: Record<string, string> = {};

    Object.keys(relationships).forEach((prop) => {
        const relationship = relationships[prop];

        if (relationship.links && Object.keys(relationship.links).length) {
            associationLinks[prop] = relationship.links.related;
        }

        // We don't have any data, don't continue with the iteration
        if (!relationship.data) {
            return;
        }

        const data = relationship.data;

        if (types.isArray(data)) {
            mappedRelations[prop] = data.map((item) => mapIncluded(item, includedMap));
        } else if (types.isObject(data)) {
            mappedRelations[prop] = mapIncluded(data, includedMap);
        } else {
            mappedRelations[prop] = null;
        }
    });

    return {
        mappedRelations: mappedRelations,
        associationLinks: associationLinks,
    };
}
