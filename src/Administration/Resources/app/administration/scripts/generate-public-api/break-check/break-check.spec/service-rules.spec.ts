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

        it('reports a member that the parent class of a service lost on the service', () => {
            const child =
                "import ApiService from './api.service';\nexport default class ExampleService extends ApiService {}";
            const parent = (body: string) => ({
                'src/app/service/api.service.ts': `export default class ApiService { ${body} }`,
            });

            expect(summary(check(serviceTree(child, parent('getHeaders() {}')), serviceTree(child, parent(''))))).toEqual([
                'Break  method_missing  service:exampleService/method:getHeaders  current, major',
            ]);
        });

        it('reports nothing for a change inside a method', () => {
            expect(
                check(classTree('    load(id) { return 1; }'), classTree('    load(id) { return this.find(id); }')),
            ).toEqual([]);
        });
    });
});
