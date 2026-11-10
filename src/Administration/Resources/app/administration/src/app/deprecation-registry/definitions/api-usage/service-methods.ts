/**
 * @sw-package framework
 */

import { apiUsageMigration, importSource, memberUsage, upgradeGuide } from '../helpers';

export default [
    apiUsageMigration({
        id: 'api.integration-service.update-admin',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description: 'integrationService.updateAdmin() is removed. Save the integration with its repository.',
        references: [
            upgradeGuide('UPGRADE-6.8.md#removed-integrationserviceupdateadmin'),
        ],
        usage: [
            memberUsage({
                service: 'integrationService',
                properties: ['updateAdmin'],
                message:
                    "integrationService.updateAdmin() is removed in 6.8. Set integration.admin and save the integration with repositoryFactory.create('integration').save(integration).",
            }),
        ],
    }),
    apiUsageMigration({
        id: 'api.validation-service.email',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description:
            'The synchronous email check of the validationService is removed, also for the email rule of the validation attribute. Validate on the server.',
        references: [
            upgradeGuide('UPGRADE-6.8.md#removed-validationserviceemail'),
        ],
        usage: [
            memberUsage({
                service: 'validationService',
                properties: ['email'],
                message:
                    'validationService.email() is removed in 6.8. Use await validationApiService.validateEmailAddress(value), it validates on the server.',
            }),
            importSource({
                sources: ['src/core/service/validation.service'],
                specifiers: ['email'],
                message:
                    'validationService.email() is removed in 6.8. Use await validationApiService.validateEmailAddress(value), it validates on the server.',
            }),
        ],
    }),
    apiUsageMigration({
        id: 'api.number-range-service.preview-pattern',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description:
            'numberRangeService.previewPattern() is removed with its type-based route. The replacement previews a saved number range by its id.',
        references: [
            upgradeGuide('UPGRADE-6.8.md#removed-numberrangeservicepreviewpattern'),
        ],
        usage: [
            memberUsage({
                service: 'numberRangeService',
                properties: ['previewPattern'],
                message:
                    'numberRangeService.previewPattern() is removed in 6.8. Use previewPatternByNumberRangeId(numberRange.id, pattern, start), which needs a saved number range.',
            }),
        ],
    }),
];
