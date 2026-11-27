/**
 * @sw-package framework
 *
 * What the break check reports for a service: one that is gone, new or private, and a change to its members.
 */
import { buildModel } from '../../read/build-model';
import type { SourceTree } from '../../source/source-tree';
import { OPTIONS, treeOf } from '../../testing/example-components';
import { classTree, MAIN, mainFile, REGISTRATION, serviceTree } from '../../testing/example-services';
import { classifyChanges } from '../break-check';
import type { Finding } from '../finding';

const NO_SERVICE = treeOf({ [MAIN]: '' });

function check(before: SourceTree, after: SourceTree): Finding[] {
    return classifyChanges(buildModel(before, OPTIONS), buildModel(after, OPTIONS));
}

/** One line per finding: its group, its rule, its element and its flag states. */
function summary(findings: Finding[]): string[] {
    return findings.map(({ group, rule, element, states }) => `${group}  ${rule}  ${element}  ${states.join(', ')}`);
}

/** The class of the example service with a comment above it. */
function marked(comment: string, body = '    load() {}'): SourceTree {
    return serviceTree(`${comment}\nexport default class ExampleService {\n${body}\n}`);
}

describe('scripts/generate-public-api/break-check/break-check service rules', () => {
    describe('a service as a whole', () => {
        it('reports a service that is gone as a break, without a line, because the head has none for it', () => {
            expect(check(classTree('    load() {}'), NO_SERVICE)).toEqual([
                {
                    group: 'Break',
                    rule: 'service_missing',
                    element: 'service:exampleService',
                    states: ['current', 'major'],
                    explanation:
                        "The service is gone, so plugin code that uses Shopware.Service('exampleService') gets undefined.",
                    fix: 'pattern "Removal". Keep the service and mark it /** @deprecated tag:v6.8.0 - Will be removed. */.',
                },
            ]);
        });

        it('allows the removal of a service that carried a released @deprecated marker', () => {
            expect(check(marked('/** @deprecated tag:v6.7.0 - Will be removed. */'), NO_SERVICE)).toMatchObject([
                {
                    group: 'Allowed',
                    rule: 'deprecated_removal',
                    element: 'service:exampleService',
                    explanation: 'The service carries @deprecated tag:v6.7.0, which announced that it goes away.',
                },
            ]);
        });

        it('keeps the removal a break while the marker names the next major', () => {
            expect(summary(check(marked('/** @deprecated tag:v6.8.0 - Will be removed. */'), NO_SERVICE))).toEqual([
                'Break  service_missing  service:exampleService  current, major',
            ]);
        });

        it('reports a service that the head registers in the current state only as a break in the major state', () => {
            const guarded = (comment: string) =>
                serviceTree(`${comment}\nexport default class ExampleService { load() {} }`, {
                    [MAIN]: mainFile(`if (!Shopware.Feature.isActive('v6.8.0.0')) {\n    ${REGISTRATION}\n}`),
                });
            const [finding] = check(classTree('    load() {}'), guarded(''));

            expect(finding).toMatchObject({
                group: 'Break',
                rule: 'service_missing',
                states: ['major'],
                explanation:
                    "The service is gone in the major state, so plugin code that uses Shopware.Service('exampleService') gets undefined.",
            });
            // A removal behind the major flag is announced by a marker for that major, which may come with the change.
            expect(
                summary(check(classTree('    load() {}'), guarded('/** @deprecated tag:v6.8.0 - Will be removed. */'))),
            ).toEqual([
                'Allowed  deprecated_removal  service:exampleService  major',
            ]);
        });

        it('reports a new service as an addition at its declaration', () => {
            expect(check(NO_SERVICE, classTree('    load() {}'))).toEqual([
                {
                    group: 'Addition',
                    rule: 'service_added',
                    element: 'service:exampleService',
                    states: ['current', 'major'],
                    explanation: 'A new public service that plugins can rely on from now on.',
                    fix: 'mark it @private if it is not meant for plugins.',
                    at: 'src/app/service/example.service.ts:1',
                },
            ]);
        });

        it('lists a private service that comes or goes as private', () => {
            const privateService = marked('/** @private */');

            expect(summary([...check(privateService, NO_SERVICE), ...check(NO_SERVICE, privateService)])).toEqual([
                'Private  service_missing  service:exampleService  current, major',
                'Private  service_added  service:exampleService  current, major',
            ]);
        });

        it('reports a service that becomes private, which a released @bc-change marker allows', () => {
            const announced = marked('/** @bc-change BecomesPrivate version:v6.7.0 */');

            expect(check(marked(''), marked('/** @private */'))).toMatchObject([
                {
                    group: 'Break',
                    rule: 'visibility_became_private',
                    element: 'service:exampleService',
                    explanation:
                        "The service became @private, which removes it from the public API, so plugin code that uses Shopware.Service('exampleService') can break with any later change.",
                    at: 'src/app/service/example.service.ts:2',
                },
            ]);
            expect(summary(check(announced, marked('/** @private */')))).toEqual([
                'Allowed  announced_change  service:exampleService  current, major',
            ]);
        });
    });

    describe('the members of a service', () => {
        it('reports a removed method and a removed property in the words of a service call', () => {
            const findings = check(classTree('    limit = 25;\n    load() {}\n    reset() {}'), classTree('    reset() {}'));

            expect(findings.map(({ rule, element, explanation, at }) => ({ rule, element, explanation, at }))).toEqual([
                {
                    rule: 'method_missing',
                    element: 'service:exampleService/method:load',
                    explanation:
                        "The method is gone, so plugin code that calls Shopware.Service('exampleService').load() throws at runtime.",
                    at: 'src/app/service/example.service.ts:1',
                },
                {
                    rule: 'property_missing',
                    element: 'service:exampleService/property:limit',
                    explanation:
                        "The property is gone, so plugin code that reads Shopware.Service('exampleService').limit gets undefined.",
                    at: 'src/app/service/example.service.ts:1',
                },
            ]);
        });

        it('applies the rules for the signature of a method', () => {
            const findings = check(
                classTree('    load(id, force) {}\n    save(item) {}\n    find(term): Item[] {}'),
                classTree('    load(force, id) {}\n    async save(item, options) {}\n    find(term): Item {}'),
            );

            expect(summary(findings)).toEqual([
                'Break  method_signature_changed  service:exampleService/method:load  current, major',
                'Break  method_signature_changed  service:exampleService/method:save  current, major',
                'Break  param_now_required  service:exampleService/method:save/param:options  current, major',
                'Break  return_type_changed  service:exampleService/method:find  current, major',
            ]);
            expect(findings[0].explanation).toBe(
                "The parameters of the method changed their order from (id, force) to (force, id), so plugin calls of Shopware.Service('exampleService').load() pass their values in the wrong place.",
            );
        });

        it('reports nothing when an API method that hands back the request chains on its promise', () => {
            const request = "    load() {\n        return this.httpClient.get('/example');\n    }";
            const handled =
                "    load() {\n        return this.httpClient.get('/example').then(ApiService.handleResponse);\n    }";

            expect(check(classTree(request), classTree(handled))).toEqual([]);
            expect(check(classTree(handled), classTree(request))).toEqual([]);
        });

        it('reports a property whose declared type changed, and a new type as info', () => {
            const findings = check(
                classTree('    limit: number;\n    label;'),
                classTree('    limit: string;\n    label: string;'),
            );

            expect(summary(findings)).toEqual([
                'Break  type_changed  service:exampleService/property:limit  current, major',
                'Info  type_widened  service:exampleService/property:label  current, major',
            ]);
            expect(findings[0].explanation).toBe(
                'The declared type of the property changed from number to string, so plugin code that reads or sets limit may no longer fit.',
            );
        });

        it('reports a new member as an addition', () => {
            expect(
                summary(check(classTree('    load() {}'), classTree('    load() {}\n    limit = 1;\n    reset() {}'))),
            ).toEqual([
                'Addition  method_added  service:exampleService/method:reset  current, major',
                'Addition  property_added  service:exampleService/property:limit  current, major',
            ]);
        });

        it('lists a change to a private member, and every change to a private service, as private', () => {
            const privateMember = (body: string) => classTree(`    /** @private */\n    ${body}`);

            expect(summary(check(privateMember('load(id) {}'), privateMember('load() {}')))).toEqual([
                'Private  param_missing  service:exampleService/method:load/param:id  current, major',
            ]);
            expect(summary(check(marked('/** @private */'), marked('/** @private */', '')))).toEqual([
                'Private  method_missing  service:exampleService/method:load  current, major',
            ]);
        });

        it('allows the removal of a member that carried a released @deprecated marker', () => {
            const before = classTree('    /** @deprecated tag:v6.7.0 - Use find instead. */\n    load() {}\n    find() {}');

            expect(summary(check(before, classTree('    find() {}')))).toEqual([
                'Allowed  deprecated_removal  service:exampleService/method:load  current, major',
            ]);
        });

        it('reports nothing for a change inside a method', () => {
            expect(
                check(classTree('    load(id) { return 1; }'), classTree('    load(id) { return this.find(id); }')),
            ).toEqual([]);
        });
    });

    describe('services that share a class', () => {
        const API_SERVICE = 'src/core/service/api.service.ts';

        function apiService(body: string): Record<string, string> {
            return { [API_SERVICE]: `export default class ApiService {\n${body}\n}` };
        }

        function subclass(name: string, body = '', parent = 'ApiService', from = 'src/core/service/api.service'): string {
            return `import ${parent} from '${from}';\nexport default class ${name} extends ${parent} {\n${body}\n}`;
        }

        /** `main.ts` registers `<name>Service` as an instance of `src/app/service/<name>.service.ts`. */
        function services(classes: Record<string, string>, files: Record<string, string>): SourceTree {
            const names = Object.keys(classes);

            return treeOf({
                [MAIN]: [
                    ...names.map((name) => `import ${name}Class from 'src/app/service/${name}.service';`),
                    ...names.map(
                        (name) => `Shopware.Application.addServiceProvider('${name}Service', () => new ${name}Class());`,
                    ),
                ].join('\n'),
                ...Object.fromEntries(names.map((name) => [`src/app/service/${name}.service.ts`, classes[name]])),
                ...files,
            });
        }

        const ORDER_AND_TAG = { order: subclass('OrderService'), tag: subclass('TagService') };

        it('reports a change to a class that several services inherit once, on the class', () => {
            const findings = check(
                services(ORDER_AND_TAG, apiService('    httpClient: AxiosInstance;\n    getHeaders() {}')),
                services(ORDER_AND_TAG, apiService('    httpClient: HttpClient;')),
            );

            expect(findings).toEqual([
                {
                    group: 'Break',
                    rule: 'method_missing',
                    element: 'class:ApiService/method:getHeaders',
                    states: ['current', 'major'],
                    explanation:
                        'The method is gone, so plugin code that calls <instance of ApiService>.getHeaders() throws at runtime.',
                    fix: 'pattern "Removal". Keep the method and mark it /** @deprecated tag:v6.8.0 - Will be removed. */.',
                    alsoAffects: ['orderService', 'tagService'],
                    at: 'src/core/service/api.service.ts:1',
                },
                expect.objectContaining({
                    group: 'Break',
                    rule: 'type_changed',
                    element: 'class:ApiService/property:httpClient',
                    alsoAffects: ['orderService', 'tagService'],
                    at: 'src/core/service/api.service.ts:2',
                }),
            ]);
        });

        it('reports a member that the class of a single service lost on the service', () => {
            const order = { order: subclass('OrderService') };

            expect(
                summary(check(services(order, apiService('    getHeaders() {}')), services(order, apiService('')))),
            ).toEqual([
                'Break  method_missing  service:orderService/method:getHeaders  current, major',
            ]);
        });

        it('leaves out a service that overrides the changed member, and keeps the change it makes itself', () => {
            const overriding = (type: string) => ({
                ...ORDER_AND_TAG,
                media: subclass('MediaService', `    httpClient: ${type};`),
            });
            const findings = check(
                services(overriding('AxiosInstance'), apiService('    httpClient: AxiosInstance;')),
                services(overriding('AxiosInstance'), apiService('    httpClient: HttpClient;')),
            );

            expect(summary(findings)).toEqual(['Break  type_changed  class:ApiService/property:httpClient  current, major']);
            expect(findings[0].alsoAffects).toEqual(['orderService', 'tagService']);
            expect(
                summary(
                    check(
                        services(overriding('AxiosInstance'), apiService('    httpClient: AxiosInstance;')),
                        services(overriding('HttpClient'), apiService('    httpClient: AxiosInstance;')),
                    ),
                ),
            ).toEqual(['Break  type_changed  service:mediaService/property:httpClient  current, major']);
        });

        it('reports a change once on the class closest to the root of a longer chain', () => {
            const chain = (syncBody: string) => ({
                sync: subclass('SyncApiService', syncBody),
                bulk: subclass('BulkService', '', 'SyncApiService', 'src/app/service/sync.service'),
                order: subclass('OrderService'),
            });
            const findings = check(
                services(chain('    sync() {}'), apiService('    getHeaders() {}')),
                services(chain(''), apiService('')),
            );

            expect(summary(findings)).toEqual([
                'Break  method_missing  class:ApiService/method:getHeaders  current, major',
                'Break  method_missing  class:SyncApiService/method:sync  current, major',
            ]);
            expect(findings.map(({ alsoAffects }) => alsoAffects)).toEqual([
                ['bulkService', 'orderService', 'syncService'],
                ['bulkService', 'syncService'],
            ]);
        });

        it('reports a change to a class that two service names share on the class', () => {
            const shared = (body: string) =>
                treeOf({
                    [MAIN]: [
                        "import SharedService from 'src/app/service/shared.service';",
                        "Shopware.Application.addServiceProvider('firstService', () => new SharedService());",
                        "Shopware.Application.addServiceProvider('secondService', () => new SharedService());",
                    ].join('\n'),
                    'src/app/service/shared.service.ts': `export default class SharedService {\n${body}\n}`,
                });
            const findings = check(shared('    load() {}'), shared(''));

            expect(summary(findings)).toEqual(['Break  method_missing  class:SharedService/method:load  current, major']);
            expect(findings[0].alsoAffects).toEqual(['firstService', 'secondService']);
        });

        it('groups nothing under two classes of one name, but still under the class they extend', () => {
            const sameName = (body: string) => ({
                first: subclass('Gateway', body),
                second: subclass('Gateway', body),
            });
            const findings = check(
                services(sameName('    load() {}'), apiService('    getHeaders() {}')),
                services(sameName(''), apiService('')),
            );

            expect(summary(findings)).toEqual([
                'Break  method_missing  class:ApiService/method:getHeaders  current, major',
                'Break  method_missing  service:firstService/method:load  current, major',
                'Break  method_missing  service:secondService/method:load  current, major',
            ]);
            expect(findings[0].alsoAffects).toEqual(['firstService', 'secondService']);
        });

        it('keeps the finding of a private service apart from the one of the class', () => {
            const withPrivate = {
                ...ORDER_AND_TAG,
                media: subclass('MediaService').replace('export default', '/** @private */\nexport default'),
            };

            expect(
                summary(
                    check(services(withPrivate, apiService('    getHeaders() {}')), services(withPrivate, apiService(''))),
                ),
            ).toEqual([
                'Break  method_missing  class:ApiService/method:getHeaders  current, major',
                'Private  method_missing  service:mediaService/method:getHeaders  current, major',
            ]);
        });
    });
});
