/**
 * @sw-package framework
 */

import Axios from 'axios';
// eslint-disable-next-line import/no-unresolved
import AxiosV1 from 'axios-v1';
import { createAxiosV0Adapter, createAxiosV1Adapter } from 'src/core/factory/http-client-adapter';

describe('core/factory/http-client-adapter', () => {
    describe('createAxiosV0Adapter', () => {
        it('should create a v0 adapter with runRequest method', () => {
            const axiosV0 = Axios.create({ baseURL: '/api' });
            const adapter = createAxiosV0Adapter(axiosV0);

            expect(adapter.runRequest).toBeDefined();
            expect(typeof adapter.runRequest).toBe('function');
        });

        it('should have isCancel method that works with axios v0 cancellation', () => {
            const axiosV0 = Axios.create({ baseURL: '/api' });
            const adapter = createAxiosV0Adapter(axiosV0);

            // The isCancel from axios v0 should work
            expect(adapter.isCancel).toBeDefined();
            expect(typeof adapter.isCancel).toBe('function');
        });

        it('should return false for non-cancellation errors', () => {
            const axiosV0 = Axios.create({ baseURL: '/api' });
            const adapter = createAxiosV0Adapter(axiosV0);

            const regularError = new Error('Regular error');
            expect(adapter.isCancel(regularError)).toBe(false);
        });
    });

    describe('createAxiosV1Adapter', () => {
        it('should create a v1 adapter with runRequest method', () => {
            const axiosV1 = AxiosV1.create({ baseURL: '/api' });
            // Type assertion needed because axios v1 types differ from v0
            // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument
            const adapter = createAxiosV1Adapter(axiosV1 as any);

            expect(adapter.runRequest).toBeDefined();
            expect(typeof adapter.runRequest).toBe('function');
        });

        it('should have isCancel method that detects CanceledError', () => {
            const axiosV1 = AxiosV1.create({ baseURL: '/api' });
            // Type assertion needed because axios v1 types differ from v0
            // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument
            const adapter = createAxiosV1Adapter(axiosV1 as any);

            // Test axios v1 style cancellation with name
            const cancelErrorByName = { name: 'CanceledError' };
            expect(adapter.isCancel(cancelErrorByName)).toBe(true);

            // Test axios v1 style cancellation with code
            const cancelErrorByCode = { code: 'ERR_CANCELED' };
            expect(adapter.isCancel(cancelErrorByCode)).toBe(true);

            // Test both
            const cancelErrorBoth = { name: 'CanceledError', code: 'ERR_CANCELED' };
            expect(adapter.isCancel(cancelErrorBoth)).toBe(true);
        });

        it('should return false for non-cancellation errors', () => {
            const axiosV1 = AxiosV1.create({ baseURL: '/api' });
            // Type assertion needed because axios v1 types differ from v0
            // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument
            const adapter = createAxiosV1Adapter(axiosV1 as any);

            const regularError = new Error('Regular error');
            expect(adapter.isCancel(regularError)).toBe(false);

            const otherError = { name: 'NetworkError', code: 'ECONNREFUSED' };
            expect(adapter.isCancel(otherError)).toBe(false);
        });

        it('should return false for non-objects', () => {
            const axiosV1 = AxiosV1.create({ baseURL: '/api' });
            // Type assertion needed because axios v1 types differ from v0
            // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument
            const adapter = createAxiosV1Adapter(axiosV1 as any);

            expect(adapter.isCancel(null)).toBe(false);
            expect(adapter.isCancel(undefined)).toBe(false);
            expect(adapter.isCancel('string')).toBe(false);
            expect(adapter.isCancel(123)).toBe(false);
        });
    });
});
