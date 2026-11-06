/**
 * @sw-package framework
 */

import { importSource, jsApiMigration, memberUsage, objectKey, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.axios-cancel-token',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'axios 1.x is the default HTTP client and has no CancelToken.',
    references: [upgradeGuide('UPGRADE-6.8.md#axios-v1-is-now-the-default-http-client')],
    usage: [
        memberUsage({ from: '*.CancelToken', to: 'an AbortController and its signal' }),
        memberUsage({ from: '*.__CANCEL__', to: 'httpClient.isCancel(error)' }),
        objectKey({ from: 'cancelToken', to: 'signal' }),
        objectKey({ from: 'useAxiosV1', to: 'the axios 1.x default' }),
        importSource({
            from: 'axios',
            specifiers: ['CancelToken', 'Cancel', 'isCancel'],
            to: 'an AbortController and its signal',
        }),
        importSource({
            from: 'src/core/factory/http.factory',
            specifiers: ['CancelToken', 'Cancel', 'isCancel'],
            to: 'an AbortController and its signal',
        }),
        memberUsage({ from: 'axios.isCancel', to: 'httpClient.isCancel(error)' }),
        memberUsage({ from: 'axios.Cancel', to: 'httpClient.isCancel(error)' }),
    ],
});
