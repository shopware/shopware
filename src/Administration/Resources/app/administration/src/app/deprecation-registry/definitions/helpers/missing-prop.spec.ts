/* eslint-disable @typescript-eslint/no-unsafe-member-access */
import { missingProp } from './missing-prop';
import { createDirectiveAttribute, createFixer, createRuleApi } from './test-utils';

describe('missingProp', () => {
    it('adds a static missing prop value', () => {
        const usage = missingProp({ prop: 'variant', value: 'secondary' });
        const node = {
            name: 'mt-button',
            startTag: {
                range: [
                    0,
                    11,
                ],
                attributes: [],
            },
            children: [],
        };
        const api = createRuleApi({ usage, node });

        usage.eslint?.report(api);

        expect(api.reports[0].fix(createFixer())).toEqual({
            method: 'insertTextAfterRange',
            range: [
                10,
                10,
            ],
            text: ' variant="secondary"',
        });
    });

    it('can add a bound prop after the component name', () => {
        const usage = missingProp({
            prop: 'is-opened',
            value: 'true',
            bind: true,
            insertPosition: 'after-name',
        });
        const node = {
            name: 'mt-floating-ui',
            startTag: {
                range: [
                    0,
                    16,
                ],
                attributes: [],
            },
            children: [],
        };
        const api = createRuleApi({ usage, node });

        usage.eslint?.report(api);

        expect(api.reports[0].fix(createFixer())).toEqual({
            method: 'insertTextAfterRange',
            range: [
                15,
                15,
            ],
            text: ' :is-opened="true"',
        });
    });

    it('adds missing props before object v-bind on the same line', () => {
        const usage = missingProp({ prop: 'variant', value: 'secondary' });
        const objectVBind = createDirectiveAttribute('bind');
        const node = {
            name: 'mt-button',
            startTag: {
                range: [
                    0,
                    30,
                ],
                loc: {
                    start: {
                        line: 1,
                    },
                },
                attributes: [objectVBind],
            },
            children: [],
        };
        const api = createRuleApi({ usage, node });

        usage.eslint?.report(api);

        expect(api.reports[0].fix(createFixer())).toEqual({
            method: 'insertTextBefore',
            target: objectVBind,
            text: 'variant="secondary" ',
        });
    });

    it('adds missing props on their own line before multiline object v-bind', () => {
        const usage = missingProp({ prop: 'variant', value: 'secondary' });
        const objectVBind = createDirectiveAttribute('bind');
        objectVBind.loc.start.line = 2;
        objectVBind.loc.start.column = 20;
        const node = {
            name: 'mt-button',
            startTag: {
                range: [
                    0,
                    30,
                ],
                loc: {
                    start: {
                        line: 1,
                    },
                },
                attributes: [objectVBind],
            },
            children: [],
        };
        const api = createRuleApi({ usage, node });

        usage.eslint?.report(api);

        expect(api.reports[0].fix(createFixer())).toEqual({
            method: 'insertTextBefore',
            target: objectVBind,
            text: 'variant="secondary"\n                    ',
        });
    });

    it('does not report when an unless prop exists', () => {
        const usage = missingProp({
            prop: 'is-opened',
            value: 'true',
            bind: true,
            unlessProps: [
                'is-opened',
                'open',
            ],
        });
        const api = createRuleApi({ usage, existingProps: ['open'] });

        usage.eslint?.report(api);

        expect(api.reports).toHaveLength(0);
    });
});
