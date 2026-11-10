/**
 * @sw-package framework
 *
 * The ESLint codemods and the rules they run: every codemod names a rule its plugin ships, and every
 * change of the 6.8 API usage data has a codemod of its own.
 */

import apiUsageEntries from '../../../eslint-rules/deprecation-rules/data/v6.8/api-usage.json';
import deprecationRules from '../../../eslint-rules/deprecation-rules';
import apiUsageRules from '../../../eslint-rules/deprecation-rules/api-usage-rules-6-8';
import coreRules from '../../../eslint-rules/core-rules';
import { CODEMODS } from '../upgrade-codemods';
import type { EslintCodemod } from '../upgrade-codemods';

const PLUGINS: Record<string, { rules: Record<string, unknown> }> = {
    'sw-deprecation-rules': deprecationRules,
    'sw-core-rules': coreRules,
};

const eslintCodemods = CODEMODS.filter((codemod): codemod is EslintCodemod => codemod.kind === 'eslint');

describe('scripts/extensionTooling/upgrade codemod rules', () => {
    it('names a rule that its plugin ships for every ESLint codemod', () => {
        const missing = eslintCodemods
            .map((codemod) => codemod.rule)
            .filter((rule) => {
                const [plugin, name] = rule.split('/');

                return !(name in (PLUGINS[plugin]?.rules ?? {}));
            });

        expect(missing).toEqual([]);
    });

    it('runs every entry of the 6.8 API usage data as a codemod of its own', () => {
        const apiUsageRuleIds = Object.keys(apiUsageRules as Record<string, unknown>).map(
            (name) => `sw-deprecation-rules/${name}`,
        );
        const codemods = eslintCodemods.filter((codemod) => apiUsageRuleIds.includes(codemod.rule));

        expect(apiUsageRuleIds).toHaveLength(apiUsageEntries.length);
        expect(codemods.map((codemod) => codemod.rule)).toEqual(apiUsageRuleIds);
        expect(codemods.every((codemod) => codemod.target === '6.8' && !codemod.optIn && codemod.scope === 'both')).toBe(
            true,
        );
    });

    it('gives every codemod an id of its own', () => {
        const ids = CODEMODS.map((codemod) => codemod.id);

        expect(new Set(ids).size).toBe(ids.length);
    });
});
