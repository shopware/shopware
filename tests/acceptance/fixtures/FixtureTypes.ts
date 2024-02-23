import { SetupFixtures } from '@fixtures/SetupFixtures';
import { WorkerFixtures } from '@fixtures/WorkerFixtures';
import { StorefrontPages } from '@page-objects/StorefrontPages';
import { AdministrationPages } from '@page-objects/AdministrationPages';
import { DataFixtures } from '@data/DataFixtures';

export interface FixtureTypes extends SetupFixtures, WorkerFixtures, DataFixtures, StorefrontPages, AdministrationPages {}
