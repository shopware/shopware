/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-axios-cancel-token');
const data = require('./no-axios-cancel-token.data');

const CANCEL = data.cancelTokenMessage;
const IS_CANCEL = data.isCancelMessage;
const V0 = data.useAxiosV0Message;
const todo = (text) => `// TODO Codemod(6.8, axios-v1): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-axios-cancel-token', rule, {
    valid: [
        {
            name: 'ignores AbortController and the facade',
            code: [
                'const controller = new AbortController();',
                "this.httpClient.get('/_action/foo', { signal: controller.signal });",
                'if (this.httpClient.isCancel(error)) {}',
            ].join('\n'),
        },
        { name: 'ignores useAxiosV1: true', code: "httpClient.request({ url: '/foo', useAxiosV1: true });" },
        { name: 'ignores the public interceptors and defaults', code: 'httpClient.interceptors.request.use(handler);\nhttpClient.defaults.headers.common.foo = 1;' },
        { name: 'ignores the CancelToken argument of messageQueueService.consume(), which 6.8 keeps', code: "this.messageQueueService.consume('async', token);" },
        { name: 'ignores other imports from axios', code: "import Axios, { AxiosError } from 'axios';" },
        { name: 'ignores isCancel of other objects', code: 'utils.isCancel(error);' },
    ],
    invalid: [
        {
            name: 'marks CancelToken and the cancelToken option',
            code: [
                'const source = this.httpClient.CancelToken.source();',
                "this.httpClient.get('/_action/foo', {",
                '    cancelToken: source.token,',
                '});',
                'const token = new Axios.CancelToken((cancel) => {});',
                'const { CancelToken } = Axios;',
            ].join('\n'),
            output: [
                todo(CANCEL),
                'const source = this.httpClient.CancelToken.source();',
                "this.httpClient.get('/_action/foo', {",
                `    ${todo(CANCEL)}`,
                '    cancelToken: source.token,',
                '});',
                todo(CANCEL),
                'const token = new Axios.CancelToken((cancel) => {});',
                todo(CANCEL),
                'const { CancelToken } = Axios;',
            ].join('\n'),
            errors: [{ message: CANCEL }, { message: CANCEL }, { message: CANCEL }, { message: CANCEL }],
        },
        {
            name: 'marks imports and isCancel of axios',
            code: [
                "import { CancelToken } from 'src/core/factory/http.factory';",
                "import axios, { isCancel } from 'axios';",
                'if (axios.isCancel(error)) {}',
            ].join('\n'),
            output: [
                todo(CANCEL),
                "import { CancelToken } from 'src/core/factory/http.factory';",
                todo(IS_CANCEL),
                "import axios, { isCancel } from 'axios';",
                todo(IS_CANCEL),
                'if (axios.isCancel(error)) {}',
            ].join('\n'),
            errors: [{ message: CANCEL }, { message: IS_CANCEL }, { message: IS_CANCEL }],
        },
        {
            name: 'marks useAxiosV1: false and the internal client fields',
            code: [
                "httpClient.request({ url: '/foo', useAxiosV1: false });",
                'httpClient.interceptorsV0.request.use(handler);',
                'const client = this.httpClient.axiosV1;',
            ].join('\n'),
            output: [
                todo(V0),
                "httpClient.request({ url: '/foo', useAxiosV1: false });",
                todo(data.internalFieldMessage('interceptorsV0')),
                'httpClient.interceptorsV0.request.use(handler);',
                todo(data.internalFieldMessage('axiosV1')),
                'const client = this.httpClient.axiosV1;',
            ].join('\n'),
            errors: [{ message: V0 }, { message: data.internalFieldMessage('interceptorsV0') }, { message: data.internalFieldMessage('axiosV1') }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('@typescript-eslint/parser'), ecmaVersion: 2022, sourceType: 'module' } }).run(
    'no-axios-cancel-token (TypeScript)',
    rule,
    {
        valid: [{ name: 'ignores other axios types', code: "import type { AxiosResponse } from 'axios';" }],
        invalid: [
            {
                name: 'marks type imports of CancelToken',
                code: "import type { CancelToken } from 'axios';",
                output: [todo(CANCEL), "import type { CancelToken } from 'axios';"].join('\n'),
                errors: [{ message: CANCEL }],
            },
        ],
    },
);
