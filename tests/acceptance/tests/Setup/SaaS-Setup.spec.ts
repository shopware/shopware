import { test } from '@fixtures/AcceptanceTest';

test.skip('Setup a saas instance.', { tag: ['@SaaS', '@Setup'] }, async ({ SaaSInstanceSetup }) => {
    await SaaSInstanceSetup();
});
