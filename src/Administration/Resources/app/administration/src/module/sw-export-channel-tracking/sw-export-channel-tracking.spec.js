/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */

import 'src/module/sw-export-channel-tracking';

const { Module } = Shopware;

describe('src/module/sw-export-channel-tracking', () => {
    it('should be registered', () => {
        const module = Module.getModuleRegistry().get('sw-export-channel-tracking');
        expect(module).toBeTruthy();
    });
});
