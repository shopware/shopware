import { missingProp } from './missing-prop';
import { createFixer, createRuleApi } from './test-utils';

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
