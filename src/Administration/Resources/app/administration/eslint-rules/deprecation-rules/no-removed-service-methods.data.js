/**
 * @sw-package framework
 *
 * Service methods 6.8 removes, with the text the `service-calls` codemod writes above their calls. Taken from the
 * `@deprecated tag:v6.8.0` markers in `integration.api.service.js`, `validation.service.js` and
 * `number-range.api.service.js`. None of the replacements is a drop-in, so the codemod only marks the calls.
 */

const EMAIL =
    "validationService.email() is removed. Use `await Shopware.Service('validationApiService').validateEmailAddress(value)`; " +
    'it is async and validates on the server.';

module.exports = {
    id: 'service-calls',
    description: 'Marks calls of service methods that 6.8 removes',
    calls: [
        {
            service: 'integrationService',
            method: 'updateAdmin',
            message:
                'integrationService.updateAdmin() is removed. Set `integration.admin` on the entity and save it with ' +
                "`Shopware.Service('repositoryFactory').create('integration').save(integration)`.",
        },
        { service: 'validationService', method: 'email', message: EMAIL },
        {
            service: 'numberRangeService',
            method: 'previewPattern',
            message:
                'numberRangeService.previewPattern() is removed with the type-based preview route. Use ' +
                '`previewPatternByNumberRangeId(numberRangeId, pattern, start)` with the id of a saved number range.',
        },
    ],
    // `import { email } from 'src/core/service/validation.service'` reaches the same function without the service.
    namedImports: [{ source: 'src/core/service/validation.service', name: 'email', message: EMAIL }],
};
