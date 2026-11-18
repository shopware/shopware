/**
 * @sw-package checkout
 */
import 'src/module/sw-extension/service';

const translator = {
    $t: (key: string) => key,
};

describe('src/module/sw-extension/service/index', () => {
    beforeEach(() => {
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockReturnValue({
            $t: translator.$t,
            $router: { push: jest.fn() },
        } as never);
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it.each([
        ['FRAMEWORK__PLUGIN_COMPOSER_OPERATION_IN_PROGRESS', 'messageComposerOperationInProgress'],
        ['FRAMEWORK__PLUGIN_COMPOSER_REQUIRE', 'messageComposerRequireFailed'],
        ['FRAMEWORK__PLUGIN_COMPOSER_REMOVE', 'messageComposerRemoveFailed'],
        ['FRAMEWORK__PLUGIN_COMPOSER_WORKING_COPY_NOT_POSSIBLE', 'messageComposerWorkingCopyNotPossible'],
        ['FRAMEWORK__PLUGIN_COMPOSER_WORKING_COPY_INTERRUPTED', 'messageComposerWorkingCopyInterrupted'],
        ['FRAMEWORK__PLUGIN_COMPOSER_DEPENDENCY_MISSING', 'messageComposerDependencyMissing'],
    ])('shows the own message for %s instead of the backend detail', (code, snippetKey) => {
        const notifications = Shopware.Service('extensionErrorService').handleErrorResponse(
            { response: { data: { errors: [{ code, title: 'Internal Server Error', detail: 'Composer output' }] } } },
            translator,
        );

        expect(notifications).toEqual([
            {
                title: 'global.default.error',
                message: `sw-extension.errors.${snippetKey}`,
                autoClose: true,
                actions: [],
            },
        ]);
    });
});
