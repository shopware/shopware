import * as Vue from 'vue';
import registry from './registry';
import { format } from './format';
import { internalHelper, oldFormat } from './legacy';
import ChangesetGenerator from './data/changeset-generator';
import EntityList from './data/entity-list';

interface TreeNode {
    name: string;
    parent: TreeNode;
}

function pick(key: string): string;
function pick(key: string, fallback: number): number;
function pick(key: string, fallback?: number): string | number {
    return fallback ?? key;
}

class ShopwareClass {
    public Registry = {
        register: registry.register,
        get: registry.get,
        format,
        pick,
        internalHelper,
        oldFormat,
    };

    public Data = {
        ChangesetGenerator,
        EntityList,
    };

    public Defaults = {
        systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b' as string,
        languageIds: ['2fbb5fe2e29a4d70aa5854ce7ce3e20b'],
        mutableLimit: Number('10'),
    };

    public Vue = Vue;

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use Registry instead.
     */
    public Legacy = {
        run: (...args: string[]): number => args.length,
    };

    /**
     * @deprecated Use Registry.get instead.
     */
    public lookup = (name: string): number | undefined => registry.get(name);

    /**
     * @experimental stableVersion:v6.9.0 feature:FIXTURE
     */
    public Labs = {
        /**
         * @private
         */
        preview: (): boolean => true,
        stable: (): boolean => false,
    };

    public _private = {
        Helpers: {
            noop: (): void => undefined,
        },
    };

    public Tree: TreeNode = { name: 'root', parent: null as unknown as TreeNode };

    public Deep = { a: { b: { c: { d: { e: { f: { g: { h: { i: 1 } } } } } } } } };

    public get Snippet() {
        return {
            t: (key: string): string => key,
            locale: 'en-GB',
        };
    }

    public get Version(): string {
        return '6.7.0.0';
    }
}

const ShopwareInstance = new ShopwareClass();

export { ShopwareClass, ShopwareInstance };
