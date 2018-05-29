import { State } from 'src/core/shopware';
import EntityStateModule from './EntityStateModule';

/**
 * @module app/state/catalog
 */
State.register('catalog', new EntityStateModule('catalog', 'catalogService').getVueX());
