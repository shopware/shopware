import { resolveExtensionName } from './telemetry.init';

describe('src/app/init/telemetry.init.ts', () => {
    describe('resolveExtensionName', () => {
        beforeEach(() => {
            Shopware.Store.get('extensions').extensionsState = {
                'my-plugin': { baseUrl: 'http://my-plugin.example.com', permissions: {} },
                'another-ext': { baseUrl: 'http://another.example.com', permissions: {} },
            };
        });

        afterEach(() => {
            Shopware.Store.get('extensions').extensionsState = {};
        });

        it('returns the technical name for a matching origin', () => {
            expect(resolveExtensionName('http://my-plugin.example.com')).toBe('my-plugin');
        });

        it('returns the correct name when multiple extensions are registered', () => {
            expect(resolveExtensionName('http://another.example.com/some/path')).toBe('another-ext');
        });

        it('returns undefined when no extension matches', () => {
            expect(resolveExtensionName('http://unknown.example.com')).toBeUndefined();
        });

        it('returns undefined for a malformed origin', () => {
            expect(resolveExtensionName('not-a-url')).toBeUndefined();
        });

        it('returns undefined when extensionsState is empty', () => {
            Shopware.Store.get('extensions').extensionsState = {};
            expect(resolveExtensionName('http://my-plugin.example.com')).toBeUndefined();
        });

        it('does not match an extension on the same host but a different port', () => {
            expect(resolveExtensionName('http://my-plugin.example.com:9090')).toBeUndefined();
        });

        it('does not match an extension on the same host but a different scheme', () => {
            expect(resolveExtensionName('https://my-plugin.example.com')).toBeUndefined();
        });

        it('returns undefined when multiple extensions share the same origin', () => {
            Shopware.Store.get('extensions').extensionsState = {
                'ext-a': { baseUrl: 'http://shared.example.com/app-a', permissions: {} },
                'ext-b': { baseUrl: 'http://shared.example.com/app-b', permissions: {} },
            };
            expect(resolveExtensionName('http://shared.example.com')).toBeUndefined();
        });

        it('returns undefined gracefully when an extension has a malformed baseUrl', () => {
            Shopware.Store.get('extensions').extensionsState = {
                'bad-ext': { baseUrl: 'not-a-url', permissions: {} },
            };
            expect(resolveExtensionName('http://my-plugin.example.com')).toBeUndefined();
        });
    });
});
