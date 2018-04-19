import { State } from 'src/core/shopware';
import EntityStateModule from './EntityStateModule';

/**
 * @module app/state/category
 */
State.register('category', new EntityStateModule('category', 'categoryService').getVueX());
