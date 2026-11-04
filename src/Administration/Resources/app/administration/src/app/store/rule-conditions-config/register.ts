/**
 * @sw-package framework
 */
import * as RuleConditionsConfigStore from './index';
import { ruleConditionsConfigStoreOptions } from './private';

Shopware.Store.register(ruleConditionsConfigStoreOptions);
Shopware._private.publish('stores', 'ruleConditionsConfig', RuleConditionsConfigStore);
