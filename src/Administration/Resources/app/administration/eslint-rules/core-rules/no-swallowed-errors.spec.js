const { RuleTester } = require('eslint');
const rule = require('./no-swallowed-errors');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const swallowedError = { messageId: 'swallowedError' };

ruleTester.run('no-swallowed-errors', rule, {
    valid: [
        {
            name: 'allows a handler that notifies the user',
            code: `save().catch(() => {
                this.createNotificationError({ message: this.$t('global.notification.unspecifiedSaveErrorMessage') });
            });`,
        },
        {
            name: 'allows a handler that logs the error',
            code: 'save().catch((error) => { console.error(error); });',
        },
        {
            name: 'allows a handler that rethrows',
            code: 'save().catch((error) => { throw error; });',
        },
        {
            name: 'allows a handler that returns the error',
            code: 'const result = await save().catch((error) => error);',
        },
        {
            name: 'allows an empty handler that says why ignoring is safe',
            code: 'router.isReady().catch(() => { /* The router reports its own navigation errors */ });',
        },
        {
            name: 'allows a fallback with a reason',
            code: 'const list = await loadList().catch(() => /* The list is optional */ null);',
        },
        {
            name: 'ignores a handler passed by reference',
            code: 'save().catch(handleError);',
        },
    ],
    invalid: [
        {
            name: 'reports an empty handler',
            code: 'this.loadGridUserSettings().catch(() => {});',
            errors: [swallowedError],
        },
        {
            name: 'reports an empty function expression',
            code: 'register(data).catch(function () {});',
            errors: [swallowedError],
        },
        {
            name: 'reports a handler that replaces the error with a constant',
            code: `const list = await this.translationService.getList().catch(() => null);
            const deleted = await deleteItem().catch(() => false);`,
            errors: [
                swallowedError,
                swallowedError,
            ],
        },
        {
            name: 'reports a handler that only returns',
            code: 'refreshToken().catch(() => { return undefined; });',
            errors: [swallowedError],
        },
        {
            name: 'reports a handler that ignores its error parameter',
            code: 'refreshToken().catch((error) => {});',
            errors: [swallowedError],
        },
    ],
});
