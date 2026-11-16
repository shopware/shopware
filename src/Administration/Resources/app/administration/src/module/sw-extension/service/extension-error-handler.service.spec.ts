/**
 * @sw-package checkout
 */
import extensionErrorHandler from './extension-error-handler.service';

function createError(code: string, detail: string): StoreApiException {
    return { code, detail, status: '500', title: 'Error' } as StoreApiException;
}

describe('src/module/sw-extension/service/extension-error-handler.service', () => {
    it.each([
        ['FRAMEWORK__PLUGIN_COMPOSER_OPERATION_IN_PROGRESS', 'messageComposerOperationInProgress'],
        ['FRAMEWORK__PLUGIN_COMPOSER_REQUIRE', 'messageComposerRequireFailed'],
        ['FRAMEWORK__PLUGIN_COMPOSER_REMOVE', 'messageComposerRemoveFailed'],
        ['FRAMEWORK__PLUGIN_COMPOSER_WORKING_COPY_NOT_POSSIBLE', 'messageComposerWorkingCopyNotPossible'],
        ['FRAMEWORK__PLUGIN_COMPOSER_WORKING_COPY_INTERRUPTED', 'messageComposerWorkingCopyInterrupted'],
        ['FRAMEWORK__PLUGIN_COMPOSER_DEPENDENCY_MISSING', 'messageComposerDependencyMissing'],
    ])('maps %s to its own message and keeps the backend detail', (code, snippetKey) => {
        const [notification] = extensionErrorHandler.mapErrors([createError(code, 'Composer output')]);

        expect(notification).toEqual({
            title: 'global.default.error',
            message: `sw-extension.errors.${snippetKey}`,
            details: 'Composer output',
        });
    });

    it('maps an unknown code to the generic failure message', () => {
        const [notification] = extensionErrorHandler.mapErrors([createError('SOME_UNKNOWN_CODE', 'Some detail')]);

        expect(notification).toEqual({
            title: 'global.default.error',
            message: 'sw-extension.errors.messageGenericFailure',
            details: 'Some detail',
        });
    });
});
