/**
 * @sw-package framework
 *
 * What the snapshot says about a registered service, for every way the sources register and declare one.
 */
import type { SourceTree } from '../../source/source-tree';
import { snapshotOf } from '../../snapshot/snapshot';
import { OPTIONS, treeOf } from '../../testing/example-components';
import {
    classTree,
    MAIN,
    mainFile,
    REGISTRATION,
    SERVICE_FILE,
    SERVICE_REPORT,
    serviceTree,
} from '../../testing/example-services';

const API_INDEX = 'src/core/service/api/index.ts';

const API_GLOB =
    "export default () => Object.values(import.meta.glob('./**/!(*.spec).{j,t}s', { eager: true, import: 'default' }));";

function files(tree: SourceTree): Record<string, string> {
    return snapshotOf(tree, OPTIONS).files;
}

/** The lines of the outlines in a report, without the empty ones. */
function outline(tree: SourceTree, report = SERVICE_REPORT): string[] {
    const [, block = ''] = /```text\n([\s\S]*?)\n```/.exec(files(tree)[report] ?? '') ?? [];

    return block.split('\n').filter((line) => line !== '');
}

/** The lines of the sections of a report, which follow the three header lines of its one service. */
function sections(tree: SourceTree, report = SERVICE_REPORT): string[] {
    return outline(tree, report).slice(3);
}

describe('scripts/generate-public-api/read/services/service-units', () => {
    describe('the object behind a registration', () => {
        it('lists the properties and methods of a class instance with their types', () => {
            const tree = classTree(
                [
                    '    limit = 25;',
                    '    label: string;',
                    '    constructor(public readonly id: string, client: Client) { this.client = client; this.reload = () => {}; }',
                    '    get total(): number { return 1; }',
                    '    load(id: string, force = false): Promise<Item> { return fetch(id); }',
                    '    onChange = (value: string): void => {};',
                ].join('\n'),
            );

            expect(sections(tree)).toEqual([
                'properties',
                '  client: any',
                '  id: string',
                '  label: string',
                '  limit: any',
                '  total: number',
                'methods',
                '  load(id: string, force?: any = false): Promise<Item>',
                '  onChange(value: string): void',
                '  reload(): any',
            ]);
        });

        it('leaves out what code outside the class cannot reach', () => {
            const tree = classTree(
                [
                    '    private cache = new Map();',
                    '    protected client: Client;',
                    '    #token = "";',
                    '    static create() {}',
                    '    constructor(private readonly http: Http) { this.cache = new Map(); }',
                    '    private refresh() {}',
                    '    load() {}',
                ].join('\n'),
            );

            expect(sections(tree)).toEqual(['methods', '  load(): any']);
        });

        it('adds the members a class inherits, and takes an overridden one from the child', () => {
            const tree = serviceTree(
                [
                    "import ApiService from '../../core/service/api.service';",
                    'export default class ExampleService extends ApiService {',
                    '    getBasicHeaders(extra: object) {}',
                    '    load() {}',
                    '}',
                ].join('\n'),
                {
                    'src/core/service/api.service.ts': [
                        "import Base from './base.service';",
                        'export default class ApiService extends Base {',
                        '    constructor(httpClient) { super(); this.httpClient = httpClient; }',
                        '    getBasicHeaders() {}',
                        '    handleResponse(response) {}',
                        '}',
                    ].join('\n'),
                    'src/core/service/base.service.ts': 'export default class Base { name = "base"; }',
                },
            );

            expect(sections(tree)).toEqual([
                'properties',
                '  httpClient: any',
                '  name: any',
                'methods',
                '  getBasicHeaders(extra: object): any',
                '  handleResponse(response: any): any',
                '  load(): any',
            ]);
        });

        it('notes a parent class that the sources do not declare', () => {
            const tree = serviceTree(
                'const ApiService = Shopware.Classes.ApiService;\nexport default class ExampleService extends ApiService { load() {} }',
            );

            expect(sections(tree)).toEqual([
                'methods',
                '  load(): any',
                'notes',
                '  unresolved parent ApiService',
            ]);
        });

        it('reads the object literal a factory returns, with the functions it names', () => {
            const service = [
                'export default function createExampleService(client) {',
                '    const reset = async () => {};',
                '    return { load, reset, limit: 25, find(id) {}, save: (item, options = {}) => {}, ...defaults };',
                '    /** @deprecated tag:v6.8.0 - Use find instead. */',
                '    function load(id, ...rest) {}',
                '}',
                'const defaults = { timeout: 1000 };',
            ].join('\n');

            expect(sections(serviceTree(service, { [MAIN]: mainFile(REGISTRATION.replace('new ', '')) }))).toEqual([
                'properties',
                '  limit: any',
                '  timeout: any',
                'methods',
                '  find(id: any): any',
                '  load(id: any, ...rest: any[]): any  [deprecated tag:v6.8.0]',
                '  reset(): Promise<any>',
                '  save(item: any, options?: any = {}): any',
            ]);
            // `new` on a function that returns an object gives that object.
            expect(sections(serviceTree(service))).toHaveLength(8);
        });

        it.each([
            ['a constant of the provider', '() => { const service = new ExampleService(); return service; }'],
            ['a constant of the function around the registration', '() => shared', 'const shared = new ExampleService();'],
            [
                'a variable that the provider fills on the first call',
                '() => { if (!lazy) { lazy = new ExampleService(); } return lazy; }',
                'let lazy: ExampleService;',
            ],
            ['a value with type syntax around it', '() => new ExampleService() as unknown as Other'],
        ])('follows a provider that returns %s', (_, provider, before = '') => {
            const main = mainFile(before, `Shopware.Application.addServiceProvider('exampleService', ${provider});`);

            expect(sections(classTree('    load() {}', { [MAIN]: main }))).toEqual(['methods', '  load(): any']);
        });

        it.each([
            ['a default export by name', 'class ExampleService { load() {} }\nexport { ExampleService as default };'],
            ['a default export below the class', 'class ExampleService { load() {} }\nexport default ExampleService;'],
            ['a re-export', "export { default } from './example.impl';"],
        ])('finds the class behind %s', (_, service) => {
            const tree = serviceTree(service, {
                'src/app/service/example.impl.ts': 'export default class Impl { load() {} }',
            });

            expect(
                outline(tree, service.includes('./example.impl') ? 'app/service/example.impl.api.md' : SERVICE_REPORT),
            ).toContain('  load(): any');
        });

        it('takes the registration as the source of an object the provider writes itself', () => {
            const main = "Shopware.Application.addServiceProvider('exampleService', () => ({ load() {} }));";

            expect(outline(treeOf({ [MAIN]: main }), 'app/main.api.md')).toEqual([
                'service exampleService  public  ts  untyped  provider',
                '  registered src/app/main.ts',
                '  source src/app/main.ts',
                'methods',
                '  load(): any',
            ]);
        });

        it('notes what an object literal holds besides the members it names', () => {
            const service = 'export default function create() { return { load() {}, ...Shopware.Utils, [key]: 1 }; }';

            expect(sections(serviceTree(service))).toEqual([
                'methods',
                '  load(): any',
                'notes',
                '  unresolved members ...Shopware.Utils',
                '  unresolved members [key]: 1',
            ]);
        });

        it('lists a service that is a function, or that its provider does not return, without members', () => {
            const callable = serviceTree('export default function parse(response) {}', {
                [MAIN]: mainFile("Shopware.Application.addServiceProvider('exampleService', () => ExampleService);"),
            });
            const nothing = serviceTree('export default function listen() { start(); }', {
                [MAIN]: mainFile(REGISTRATION.replace('new ', '')),
            });

            expect(outline(callable)).toEqual([
                'service exampleService  public  ts  untyped  provider',
                '  registered src/app/main.ts',
                '  source src/app/service/example.service.ts',
            ]);
            expect(outline(nothing, 'app/main.api.md')).toEqual([
                'service exampleService  public  ts  untyped  provider',
                '  registered src/app/main.ts',
                '  source src/app/main.ts',
            ]);
        });

        it('notes a provider it cannot follow, and lists the service with its registration', () => {
            const main = [
                'const RepositoryFactory = Shopware.Classes._private.RepositoryFactory;',
                "Shopware.Application.addServiceProvider('exampleService', () => new RepositoryFactory(Shopware.Data));",
            ].join('\n');

            expect(outline(treeOf({ [MAIN]: main }), 'app/main.api.md')).toEqual([
                'service exampleService  public  ts  untyped  provider',
                '  registered src/app/main.ts',
                '  source src/app/main.ts',
                'notes',
                '  unresolved provider new RepositoryFactory(Shopware.Data)',
            ]);
        });
    });

    describe('names of the Shopware object', () => {
        const shopware = [
            "import RepositoryFactory from 'src/core/data/repository-factory.data';",
            "import data from 'src/core/data/index';",
            'class ShopwareClass {',
            '    public Classes = { _private: { RepositoryFactory } };',
            '    public Data = data;',
            '}',
            'const ShopwareInstance = new ShopwareClass();',
            'export { ShopwareClass, ShopwareInstance };',
        ].join('\n');
        const sources = {
            'src/core/shopware.ts': shopware,
            'src/core/data/repository-factory.data.ts':
                'export default class RepositoryFactory { create(entityName: string) {} }',
            'src/core/data/index.ts':
                "import EntityHydrator from './entity-hydrator.data';\nexport default { EntityHydrator };",
            'src/core/data/entity-hydrator.data.ts': 'export default class EntityHydrator { hydrate(response) {} }',
        };

        it('follows a class that the provider reads from the Shopware object', () => {
            const main = [
                'const RepositoryFactory = Shopware.Classes._private.RepositoryFactory;',
                "Shopware.Application.addServiceProvider('exampleService', () => new RepositoryFactory());",
            ].join('\n');

            expect(outline(treeOf({ ...sources, [MAIN]: main }), 'core/data/repository-factory.data.api.md')).toEqual([
                'service exampleService  public  ts  untyped  provider',
                '  registered src/app/main.ts',
                '  source src/core/data/repository-factory.data.ts',
                'methods',
                '  create(entityName: string): any',
            ]);
        });

        it('follows a class that a destructuring takes from the Shopware object', () => {
            const main = [
                'const { EntityHydrator: Hydrator } = Shopware.Data;',
                "Shopware.Application.addServiceProvider('exampleService', () => new Hydrator());",
            ].join('\n');

            expect(outline(treeOf({ ...sources, [MAIN]: main }), 'core/data/entity-hydrator.data.api.md')).toContain(
                '  hydrate(response: any): any',
            );
        });
    });

    describe('registrations', () => {
        it('reads Shopware.Service().register like addServiceProvider, also in a chain of calls', () => {
            const main = mainFile(
                "Shopware.Service().register('firstService', () => new ExampleService());",
                "Application.addServiceProvider('secondService', () => new ExampleService()).addServiceProvider('thirdService', () => new ExampleService());",
            );

            expect(outline(classTree('', { [MAIN]: main })).filter((line) => line.startsWith('service '))).toEqual([
                'service firstService  public  ts  untyped  provider',
                'service secondService  public  ts  untyped  provider',
                'service thirdService  public  ts  untyped  provider',
            ]);
        });

        it('registers the class of every file below the API service folder under the name its constructor sets', () => {
            const tree = treeOf({
                [API_INDEX]: API_GLOB,
                'src/core/service/api/cache.api.service.js':
                    "class CacheApiService { constructor(httpClient) { this.httpClient = httpClient; this.name = 'cacheApiService'; } clear() {} }\nexport default CacheApiService;",
            });

            expect(outline(tree, 'core/service/api/cache.api.service.api.md')).toEqual([
                'service cacheApiService  public  js  untyped  api-service',
                '  registered src/core/service/api/index.ts',
                '  source src/core/service/api/cache.api.service.js',
                'properties',
                '  httpClient: any',
                '  name: any',
                'methods',
                '  clear(): any',
            ]);
        });

        it.each([
            [
                'an API service without a literal name',
                {
                    [API_INDEX]: API_GLOB,
                    'src/core/service/api/cache.api.service.js':
                        'export default class Cache { constructor(name) { this.name = name; } }',
                },
                'src/core/service/api/cache.api.service.js: an API service has to export a class by default whose constructor sets this.name to a literal',
            ],
            [
                'an index that loads the API services in another way',
                { [API_INDEX]: "export default () => Object.values(import.meta.glob('./*.js'));" },
                "src/core/service/api/index.ts no longer loads the API services with import.meta.glob('./**/!(*.spec).{j,t}s')",
            ],
            [
                'a registration under a name that is no literal',
                { [MAIN]: 'Shopware.Application.addServiceProvider(name, () => ({}));' },
                'src/app/main.ts:1: a service needs a literal name and a provider',
            ],
            [
                'two different objects under one name',
                {
                    [MAIN]: mainFile(REGISTRATION, "Shopware.Service().register('exampleService', () => ({ load() {} }));"),
                    [SERVICE_FILE]: 'export default class ExampleService {}',
                },
                'The service exampleService is registered with two different objects, in src/app/main.ts and in src/app/main.ts.',
            ],
        ])('rejects %s', (_, sources, message) => {
            expect(() => files(treeOf(sources))).toThrow(message);
        });

        it('accepts the two files of core that pass a service on under a name they get at runtime', () => {
            const tree = treeOf({
                'src/core/factory/service.factory.ts':
                    'export default { register: (name, service) => Shopware.Application.addServiceProvider(name, service) };',
                'src/app/init-pre/api-services.init.ts':
                    'export default () => Shopware.Application.addServiceProvider(serviceName, () => service);',
            });

            expect(JSON.parse(files(tree)['index.json'])).toMatchObject({ services: {} });
        });

        it('lists a service once that core registers twice with the same object', () => {
            const tree = classTree('    load() {}', {
                [MAIN]: mainFile(REGISTRATION, "Shopware.Service().register('exampleService', () => new ExampleService());"),
            });

            expect(outline(tree).filter((line) => line.startsWith('service '))).toHaveLength(1);
        });

        it('marks a service that is registered in one flag state only, with its members', () => {
            const main = mainFile(`if (!Shopware.Feature.isActive('v6.8.0.0')) {\n    ${REGISTRATION}\n}`);

            expect(outline(classTree('    load() {}', { [MAIN]: main }))).toEqual([
                'service exampleService  public  ts  untyped  provider  [until v6.8.0]',
                '  registered src/app/main.ts',
                '  source src/app/service/example.service.ts',
                'methods',
                '  load(): any  [until v6.8.0]',
            ]);
        });
    });

    describe('visibility and markers', () => {
        it.each([
            ['its class', '/** @private */\nexport default class ExampleService { load() {} }'],
            [
                'the default export of its class',
                'class ExampleService { load() {} }\n\n/**\n * @private\n */\nexport default ExampleService;',
            ],
            [
                'the factory that returns it',
                '/** @private */\n// eslint-disable-next-line no-restricted-syntax\nexport default function ExampleService() { return { load() {} }; }',
            ],
        ])('marks every line of a service with a private tag above %s', (_, service) => {
            expect(outline(serviceTree(service))).toEqual([
                'service exampleService  private  ts  untyped  provider  [private]',
                '  registered src/app/main.ts  [private]',
                '  source src/app/service/example.service.ts  [private]',
                '  visibility-source export  [private]',
                'methods',
                '  load(): any  [private]',
            ]);
        });

        it('ignores a private tag in the file header and reads one on a member', () => {
            const service = [
                '/**',
                ' * @private',
                ' */',
                "import Base from './base';",
                '',
                '// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations',
                'export default class ExampleService {',
                '    /** @private */',
                '    refresh() {}',
                '    load() {}',
                '}',
            ].join('\n');

            expect(outline(serviceTree(service))).toEqual([
                'service exampleService  public  ts  untyped  provider',
                '  registered src/app/main.ts',
                '  source src/app/service/example.service.ts',
                'methods',
                '  load(): any',
                '  refresh(): any  [private]',
            ]);
        });

        it('shows the markers of the service and of its members', () => {
            const service = [
                '/** @deprecated tag:v6.8.0 - Will be removed. */',
                'export default class ExampleService {',
                '    /** @bc-change ParameterRemoval version:v6.8.0 parameter:force */',
                '    load(id, force) {}',
                '}',
            ].join('\n');

            expect(outline(serviceTree(service))).toEqual([
                'service exampleService  public  ts  untyped  provider  [deprecated tag:v6.8.0]',
                '  registered src/app/main.ts',
                '  source src/app/service/example.service.ts',
                'methods',
                '  load(id: any, force: any): any  [bc-change ParameterRemoval v6.8.0 force]',
            ]);
        });
    });

    describe('the files of a service', () => {
        it('writes one report per source file, for all the services it declares, and an index entry per service', () => {
            const main = mainFile(
                "Shopware.Application.addServiceProvider('zebraService', () => new ExampleService());",
                "Shopware.Application.addServiceProvider('alphaService', () => new ExampleService());",
            );
            const generated = files(classTree('    load() {}', { [MAIN]: main }));

            expect(Object.keys(generated).sort()).toEqual([SERVICE_REPORT, 'index.json']);
            expect(generated[SERVICE_REPORT].split('\n').slice(0, 5)).toEqual([
                '# Services of src/app/service/example.service.ts',
                '',
                '<!-- Generated by `composer admin:generate-public-api`. Do not edit. -->',
                '',
                'API report of the services of `src/app/service/example.service.ts` in both flag states.',
            ]);
            expect(generated[SERVICE_REPORT]).toContain(
                ['  load(): any', '', 'service zebraService  public  ts  untyped  provider'].join('\n'),
            );
            expect(JSON.parse(generated['index.json'])).toMatchObject({
                services: {
                    alphaService: { file: 'app/service/example.service', visibility: 'public' },
                    zebraService: { file: 'app/service/example.service', visibility: 'public' },
                },
            });
        });

        it('says what the ServiceContainer interface declares for the name', () => {
            const container = (entry: string): Record<string, string> => ({
                'src/global.types.ts': `declare global {\n    interface ServiceContainer extends SubContainer<'service'> {\n        ${entry}\n    }\n}`,
            });
            const header = (types: Record<string, string>): string => outline(classTree('', types))[0];

            expect(header(container('exampleService: ExampleService;'))).toBe(
                'service exampleService  public  ts  typed  provider',
            );
            expect(header(container('exampleService: $TSFixMe;'))).toBe('service exampleService  public  ts  any  provider');
            expect(header(container('otherService: OtherService;'))).toBe(
                'service exampleService  public  ts  untyped  provider',
            );
        });
    });
});
