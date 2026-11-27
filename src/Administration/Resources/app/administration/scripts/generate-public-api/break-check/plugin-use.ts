/**
 * @sw-package framework
 *
 * How plugin code reaches the members of a unit, in the words of a finding. A plugin overrides a method of a
 * component and calls the original through `$super`, while it calls a service or a store from outside. The
 * rules are the same for all of them, so each kind of unit brings the clauses that differ.
 */

/**
 * @private
 *
 * The clauses of the findings about a method or a property of one unit. Each one completes a sentence such as
 * `The method is gone, so <clause>.`
 */
export interface PluginUse {
    /** How plugin code names the unit itself. */
    unit: string;
    /** What happens to plugin code once the unit is gone. */
    withoutUnit: string;
    withoutMethod: (method: string) => string;
    withoutProperty: (property: string) => string;
    /** Who gets a Promise from a method that returned a plain value before. */
    gettingPromise: (method: string) => string;
    /** Who chained on the Promise that a method no longer returns. */
    losingPromise: (method: string) => string;
    /** The calls that pass arguments to the method, as the subject of a clause in the plural. */
    calls: (method: string) => string;
    withoutParameter: (method: string) => string;
    /** What a new optional parameter means for existing plugin code, `null` when it means nothing. */
    newOptionalParameter: string | null;
}

const COMPONENT_MEMBERS: Omit<PluginUse, 'unit' | 'withoutUnit'> = {
    withoutMethod: (method) => `plugin overrides that call $super('${method}') throw at runtime`,
    withoutProperty: (property) => `plugin overrides that read this.${property} get undefined`,
    gettingPromise: (method) => `plugin overrides that use the return value of $super('${method}') get a Promise instead`,
    losingPromise: (method) => `plugin overrides that chain a .then() on $super('${method}') fail`,
    calls: (method) => `plugin calls and $super('${method}', ...) calls`,
    withoutParameter: (method) => `plugin overrides of ${method} that read it get undefined`,
    newOptionalParameter: 'Plugin overrides that call $super with explicit arguments do not pass it on.',
};

/** @private */
export function componentUse(name: string): PluginUse {
    return {
        unit: name,
        withoutUnit: `plugin templates that render ${name} fail to resolve it`,
        ...COMPONENT_MEMBERS,
    };
}

/** The wording for an object that plugin code gets from an accessor and calls from outside. */
function objectUse(unit: string): Omit<PluginUse, 'withoutUnit'> {
    const member = (memberName: string): string => `${unit}.${memberName}`;

    return {
        unit,
        withoutMethod: (method) => `plugin code that calls ${member(method)}() throws at runtime`,
        withoutProperty: (property) => `plugin code that reads ${member(property)} gets undefined`,
        gettingPromise: (method) => `plugin code that uses the return value of ${member(method)}() gets a Promise instead`,
        losingPromise: (method) => `plugin code that chains a .then() on ${member(method)}() fails`,
        calls: (method) => `plugin calls of ${member(method)}()`,
        withoutParameter: (method) => `the value that plugin calls of ${member(method)}() pass for it is ignored`,
        newOptionalParameter: null,
    };
}

/** @private */
export function serviceUse(name: string): PluginUse {
    const use = objectUse(`Shopware.Service('${name}')`);

    return { ...use, withoutUnit: `plugin code that uses ${use.unit} gets undefined` };
}

/**
 * @private
 *
 * The wording for an object of the global `Shopware` object, which plugin code names by its path. The object
 * is gone as a unit while the object above it still holds it, when the snapshot can no longer read it.
 */
export function globalUse(path: string): PluginUse {
    return {
        ...objectUse(path),
        withoutUnit: `the snapshot no longer lists the members of ${path}, and plugin code that uses them can break unnoticed`,
    };
}

/** @private */
export function storeUse(name: string): PluginUse {
    const use = objectUse(`Shopware.Store.get('${name}')`);

    return { ...use, withoutUnit: `plugin code that calls ${use.unit} throws at runtime` };
}

/**
 * @private
 *
 * Plugin code reaches the members of a class through every service that is an instance of it. A class is never
 * reported as a whole, so `withoutUnit` only completes the type.
 */
export function classUse(name: string): PluginUse {
    const use = objectUse(`<instance of ${name}>`);

    return { ...use, withoutUnit: `plugin code that uses ${use.unit} breaks` };
}
