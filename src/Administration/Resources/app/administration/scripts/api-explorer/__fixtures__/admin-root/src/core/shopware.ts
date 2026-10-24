import { Registry } from './factory';
import { format } from './format';

export const createRegistry = (): Registry => new Registry();

export const Shopware = {
    createRegistry,
    format,
    version: FIXTURE_GLOBAL,
};
