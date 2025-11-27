import {
  CurrentsFixtures,
  CurrentsWorkerFixtures,
  fixtures as currentsFixtures,
} from "@currents/playwright";
   import { test as base } from "@playwright/test";
   
   export const CurrentsTest = base.extend<CurrentsFixtures, CurrentsWorkerFixtures>({
  ...currentsFixtures.baseFixtures,
  ...currentsFixtures.coverageFixtures,
});

export { CurrentsTest as test };