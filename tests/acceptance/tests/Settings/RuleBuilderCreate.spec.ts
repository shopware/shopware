import { test } from '@fixtures/AcceptanceTest';

test('As an admin user, I want to create a rule', { tag: '@Rule' }, async ({

    AdminRuleCreate,
    ShopAdmin,
    IdProvider,
    TestDataService,


}) => {
    const uniqueId = IdProvider.getIdPair().id;
    const ruleName = `Test rule - ${uniqueId}`;
    const testTag: string = await TestDataService.createTag(`Test tag - ${uniqueId}`);

    //GIVEN an admin user is logged in
    //WHEN the admin user creates a rule
    await ShopAdmin.goesTo(AdminRuleCreate.url());
    //AND the user fills out all available general fields
    await AdminRuleCreate.nameInput.fill(ruleName);
    await AdminRuleCreate.priorityInput.fill('1');
    await AdminRuleCreate.page.locator('.sw-field--rule-description').fill('This is a test rule, that is created to test the creation of rules.');
    await AdminRuleCreate.filtersResultPopoverSelectionList.getByText('Payment').click();
    await AdminRuleCreate.filtersResultPopoverSelectionList.getByText('Price').click();
    await AdminRuleCreate.filtersResultPopoverSelectionList.getByText('FlowBuilder').click();
    await AdminRuleCreate.filtersResultPopoverSelectionList.getByText('Shipping').click();
    await AdminRuleCreate.page.locator('.sw-select__selection').getByLabel('Tags').click();
    await AdminRuleCreate.filtersResultPopoverSelectionList.getByText('test').click();

    //AND uses at least one condition per Group and input field type

    //AND uses at least one "AND"-, "OR"-, sub-conditions and rule filter

    //AND uses the "At least One/All" selector as well as "Is one of/Is none of/Is empty" operator at least once

    //THEN the rule can be saved correctly

    //AND it can be validated, that the rule is exactly structured as the user defined it
});
