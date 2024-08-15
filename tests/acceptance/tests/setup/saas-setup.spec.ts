import { test } from '@fixtures/AcceptanceTest';

test('setup saas shop @setup', async ({ SaaSInstanceSetup }) => {
    await SaaSInstanceSetup();
});