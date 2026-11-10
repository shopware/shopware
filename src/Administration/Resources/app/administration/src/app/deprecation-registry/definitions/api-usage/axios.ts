/**
 * @sw-package framework
 */

import { apiUsageMigration, importSource, memberUsage, objectKey, upgradeGuide } from '../helpers';

const CANCEL_TOKEN_MESSAGE =
    'axios 1.x is the default HTTP client in 6.8. Replace the CancelToken with an AbortController and pass its signal.';
const IS_CANCEL_MESSAGE =
    'axios 1.x is the default HTTP client in 6.8. Detect a cancelled request with httpClient.isCancel(error).';

export default apiUsageMigration({
    id: 'api.axios-v1',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'axios 1.x is the default HTTP client. It does not support the CancelToken of axios 0.x, and its errors differ; review the error handling of your requests.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#axios-v1-is-now-the-default-http-client'),
    ],
    usage: [
        memberUsage({ object: 'axios', properties: ['CancelToken'], message: CANCEL_TOKEN_MESSAGE }),
        memberUsage({ object: 'Axios', properties: ['CancelToken'], message: CANCEL_TOKEN_MESSAGE }),
        memberUsage({ properties: ['__CANCEL__'], message: IS_CANCEL_MESSAGE }),
        objectKey({ names: ['cancelToken'], message: CANCEL_TOKEN_MESSAGE }),
        importSource({
            sources: [
                'axios',
                'src/core/factory/http.factory',
            ],
            specifiers: [
                'CancelToken',
                'Cancel',
                'isCancel',
            ],
            message: `${CANCEL_TOKEN_MESSAGE} Detect a cancelled request with httpClient.isCancel(error).`,
        }),
        memberUsage({ object: 'axios', properties: ['isCancel', 'Cancel'], message: IS_CANCEL_MESSAGE }),
        memberUsage({ object: 'Axios', properties: ['isCancel', 'Cancel'], message: IS_CANCEL_MESSAGE }),
        objectKey({
            names: ['useAxiosV1'],
            message: 'axios 1.x is the default HTTP client in 6.8. Remove useAxiosV1 and migrate the request to axios 1.x.',
        }),
    ],
});
