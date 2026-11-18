/**
 * @sw-package checkout
 */
import { composerErrorSnippets } from './composer-error-snippets';
import enSnippets from '../snippet/en.json';
import deSnippets from '../snippet/de.json';

describe('src/module/sw-extension/service/composer-error-snippets', () => {
    it.each([
        ['FRAMEWORK__PLUGIN_COMPOSER_OPERATION_IN_PROGRESS', 'messageComposerOperationInProgress'],
        ['FRAMEWORK__PLUGIN_COMPOSER_REQUIRE', 'messageComposerRequireFailed'],
        ['FRAMEWORK__PLUGIN_COMPOSER_REMOVE', 'messageComposerRemoveFailed'],
        ['FRAMEWORK__PLUGIN_COMPOSER_WORKING_COPY_NOT_POSSIBLE', 'messageComposerWorkingCopyNotPossible'],
        ['FRAMEWORK__PLUGIN_COMPOSER_WORKING_COPY_INTERRUPTED', 'messageComposerWorkingCopyInterrupted'],
        ['FRAMEWORK__PLUGIN_COMPOSER_DEPENDENCY_MISSING', 'messageComposerDependencyMissing'],
    ])('maps %s to its error message', (code, snippetKey) => {
        expect(composerErrorSnippets[code as keyof typeof composerErrorSnippets]).toEqual({
            title: 'global.default.error',
            message: `sw-extension.errors.${snippetKey}`,
        });
    });

    it('maps only the six Composer error codes', () => {
        expect(Object.keys(composerErrorSnippets)).toHaveLength(6);
    });

    it.each([
        ['en', enSnippets],
        ['de', deSnippets],
    ])('points every message to a snippet that exists in %s', (_language, snippets) => {
        const errorSnippets: Record<string, unknown> = snippets['sw-extension'].errors;

        Object.values(composerErrorSnippets).forEach(({ message }) => {
            const key = message.replace('sw-extension.errors.', '');

            expect(errorSnippets[key]).toEqual(expect.any(String));
        });
    });
});
