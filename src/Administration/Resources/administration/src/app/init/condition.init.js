import ConditionStore from 'src/core/data/ConditionStore';

export default function initializeConditions() {
    const factoryContainer = this.getContainer('factory');
    const serviceContainer = this.getContainer('service');
    const stateFactory = factoryContainer.state;
    const ruleConditionStore = new ConditionStore(serviceContainer.ruleConditionDataProviderService);
    stateFactory.registerStore('ruleCondition', ruleConditionStore);
}
