/**
 * @sw-package framework
 *
 * What the break check reports for a change to the parameters, the result and the return type of a method.
 */
import { check, memberSummary, methods } from './sources';

describe('scripts/generate-public-api/break-check/break-check method rules', () => {
    it('reports a removed parameter, also the last one', () => {
        expect(memberSummary(check(methods('load(id, force) {}'), methods('load() {}')))).toEqual([
            'Break  param_missing  method:load/param:force',
            'Break  param_missing  method:load/param:id',
        ]);
    });

    it('allows the removal of a parameter that a released @bc-change marker names', () => {
        const before = methods('/** @bc-change ParameterRemoval version:v6.7.0 parameter:force */\nload(id, force) {}');

        expect(memberSummary(check(before, methods('load() {}')))).toEqual([
            'Break  param_missing  method:load/param:id',
            'Allowed  announced_change  method:load/param:force',
        ]);
    });

    it('reports a new parameter as a break when it is required and as an addition when it is optional', () => {
        expect(memberSummary(check(methods('load(id) {}'), methods('load(id, force, limit = 25, ...rest) {}')))).toEqual([
            'Break  param_now_required  method:load/param:force',
            'Addition  param_added  method:load/param:limit',
            'Addition  param_added  method:load/param:rest',
        ]);
    });

    it('reports a parameter that lost its default', () => {
        expect(memberSummary(check(methods('load(id = null) {}'), methods('load(id) {}')))).toEqual([
            'Break  param_now_required  method:load/param:id',
        ]);
    });

    it('reports a changed default of a parameter, which a released @bc-change marker with the new value allows', () => {
        const marked = (value: string) =>
            methods(
                `/** @bc-change ParameterDefaultValueChange version:v6.7.0 parameter:limit default:${value} */\nload(limit = 25) {}`,
            );

        expect(memberSummary(check(methods('load(limit = 25) {}'), methods('load(limit = 50) {}')))).toEqual([
            'Break  param_default_changed  method:load/param:limit',
        ]);
        expect(memberSummary(check(marked('50'), methods('load(limit = 50) {}')))).toEqual([
            'Allowed  announced_change  method:load/param:limit',
        ]);
        expect(memberSummary(check(marked('100'), methods('load(limit = 50) {}')))).toEqual([
            'Break  param_default_changed  method:load/param:limit',
        ]);
    });

    it('names the removed parameter in the middle, not the one that moved up', () => {
        const [finding, ...others] = check(methods('load(id, force, limit) {}'), methods('load(id, limit) {}'));

        expect(others).toEqual([]);
        expect(finding).toMatchObject({
            group: 'Break',
            rule: 'param_missing',
            element: 'component:sw-example-card/method:load/param:force',
            explanation:
                "The parameter force is gone and limit moved up, so plugin calls and $super('load', ...) calls pass their values in the wrong place.",
        });
    });

    it('allows the removal of a parameter in the middle that a released @bc-change marker names', () => {
        const before = methods(
            '/** @bc-change ParameterRemoval version:v6.7.0 parameter:force */\nload(id, force, limit) {}',
        );

        expect(memberSummary(check(before, methods('load(id, limit) {}')))).toEqual([
            'Allowed  announced_change  method:load/param:force',
        ]);
    });

    it('reports a parameter that moved to another position, also with a new name behind it', () => {
        expect(memberSummary(check(methods('load(id, force) {}'), methods('load(force, limit) {}')))).toEqual([
            'Break  param_missing  method:load/param:id',
            'Break  param_now_required  method:load/param:limit',
        ]);
    });

    it('reports a new parameter in front of an existing one as a break, also when it is optional', () => {
        const [finding, ...others] = check(methods('load(id, force) {}'), methods('load(id, limit = 25, force) {}'));

        expect(others).toEqual([]);
        expect(finding).toMatchObject({
            group: 'Break',
            rule: 'method_signature_changed',
            element: 'component:sw-example-card/method:load/param:limit',
            explanation:
                "The new parameter limit stands in front of force, so plugin calls and $super('load', ...) calls pass the value meant for force as limit.",
        });
    });

    it('follows a destructured parameter by its pattern when it moves', () => {
        expect(
            memberSummary(check(methods('load(id, { a } = {}) {}'), methods('load(id, limit = 25, { a } = {}) {}'))),
        ).toEqual([
            'Break  method_signature_changed  method:load/param:limit',
        ]);

        const before = methods(
            '/** @bc-change ParameterRemoval version:v6.7.0 parameter:force */\nload(id, force, { a } = {}) {}',
        );

        expect(memberSummary(check(before, methods('load(id, { a } = {}) {}')))).toEqual([
            'Allowed  announced_change  method:load/param:force',
        ]);
    });

    it('names a destructured parameter by its pattern', () => {
        const [finding, ...others] = check(methods('load(id, { a }) {}'), methods('load(id, { a, b }) {}'));

        expect(others).toEqual([]);
        expect(finding).toMatchObject({
            rule: 'method_param_renamed',
            element: 'component:sw-example-card/method:load/param:{a,b}',
            explanation: 'The parameter {a} is now called {a,b}. It kept its position, so calls pass the same value.',
        });
    });

    it('allows the removal of a destructured parameter that a released @bc-change marker names by its pattern', () => {
        const before = methods('/** @bc-change ParameterRemoval version:v6.7.0 parameter:{a} */\nload(id, { a }) {}');

        expect(memberSummary(check(before, methods('load(id) {}')))).toEqual([
            'Allowed  announced_change  method:load/param:{a}',
        ]);
    });

    it('reports a renamed parameter as a hint and reordered parameters as a break', () => {
        expect(memberSummary(check(methods('load(id, force) {}'), methods('load(key, force) {}')))).toEqual([
            'Info  method_param_renamed  method:load/param:key',
        ]);
        expect(memberSummary(check(methods('load(id, force) {}'), methods('load(force, id) {}')))).toEqual([
            'Break  method_signature_changed  method:load',
        ]);
    });

    it('reports a method that starts or stops returning a Promise', () => {
        expect(memberSummary(check(methods('load() {}'), methods('async load() {}')))).toEqual([
            'Break  method_signature_changed  method:load',
        ]);
        expect(memberSummary(check(methods('async load() {}'), methods('load() {}')))).toEqual([
            'Break  method_signature_changed  method:load',
        ]);
        expect(
            memberSummary(check(methods('load() { return this.repository.save(); }'), methods('async load() {}'))),
        ).toEqual(['Break  method_signature_changed  method:load']);
    });

    it('reports nothing when a method that returns a promise chain becomes async', () => {
        const chained = methods('load() { return this.repository.save().then(() => this.reload()); }');
        const awaited = methods('async load() { await this.repository.save(); await this.reload(); }');

        expect(check(chained, awaited)).toEqual([]);
        expect(check(awaited, chained)).toEqual([]);
        expect(check(methods('load() { return Promise.all([]); }'), methods('async load() {}'))).toEqual([]);
    });

    it('reports a typed method that stops returning a Promise once, as a return type change', () => {
        const typed = (type: string, marker = '') => ({
            options: `methods: {\n${marker}\nload(): ${type} { return ''; }\n}`,
            extension: 'ts' as const,
        });
        const marker = '/** @bc-change ReturnTypeNarrowing version:v6.7.0 type:string */';

        expect(memberSummary(check(typed('Promise<string>'), typed('string')))).toEqual([
            'Break  return_type_changed  method:load',
        ]);
        expect(memberSummary(check(typed('Promise<string>', marker), typed('string')))).toEqual([
            'Allowed  announced_change  method:load',
        ]);
    });

    it('keeps a return type change a break when the marker names another type', () => {
        const typed = (type: string, marker = '') => ({
            options: `methods: {\n${marker}\nload(): ${type} { return []; }\n}`,
            extension: 'ts' as const,
        });
        const marker = '/** @bc-change ReturnTypeWidening version:v6.7.0 type:unknown[] */';

        expect(memberSummary(check(typed('string[]', marker), typed('number[]')))).toEqual([
            'Break  return_type_changed  method:load',
        ]);
    });

    it('reports another declared return type as a break that a released @bc-change marker allows', () => {
        const typed = (type: string, marker = '') => ({
            options: `methods: {\n${marker}\nload(): ${type} { return []; }\n}`,
            extension: 'ts' as const,
        });

        expect(memberSummary(check(typed('string[]'), typed('number[]')))).toEqual([
            'Break  return_type_changed  method:load',
        ]);
        expect(
            memberSummary(
                check(
                    typed('string[]', '/** @bc-change ReturnTypeWidening version:v6.7.0 type:unknown[] */'),
                    typed('unknown[]'),
                ),
            ),
        ).toEqual(['Allowed  announced_change  method:load']);
    });

    it('compares no parameters of a method that is no function in the source', () => {
        expect(check(methods('load(id) {}'), methods('load: loadById'))).toEqual([]);
    });

    it('reports nothing when only the code inside a method changes', () => {
        expect(check(methods('load(id) { return id; }'), methods('load(id) { return this.find(id) ?? null; }'))).toEqual([]);
    });
});
