import { SetupFixtures } from '@fixtures/SetupFixtures';
import { WorkerFixtures } from '@fixtures/WorkerFixtures';
import { StorefrontPages } from '@page-objects/StorefrontPages';

export interface FixtureTypes extends SetupFixtures, WorkerFixtures, StorefrontPages {}
