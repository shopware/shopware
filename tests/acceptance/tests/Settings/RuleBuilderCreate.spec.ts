import { test } from '@fixtures/AcceptanceTest';

test('As an admin user, I want to create a rule', { tag: '@Rule' }, async ({
    AdminRuleDetail,
    AdminRuleListing,
    ShopAdmin,
    IdProvider,
    TestDataService,
    CreateRule,
}) => {

    const { uuid: ruleId, id: uniqueId } = IdProvider.getIdPair();
    const { id: taxId, name: taxName } = (await TestDataService.createTaxRate());
    const today = new Date();
    const yesterday = new Date();
    yesterday.setHours(today.getHours() - 30);

    const testConfig = {
        ruleId,
        ruleName: `Test rule - ${uniqueId}`,
        ruleTypes: ['Price', 'Shipping', 'Payment', 'Flow Builder'],
        rulePriority: 1,
        ruleDescription: 'This is a test rule, created to test the Rule Builder.',
        ruleTag: (await TestDataService.createTag(`Test tag - ${uniqueId}`)).name,
        taxId,
        taxName,
        customerSurname: 'Schmitz-Rimpler',
        fromDate: yesterday.toISOString().split('.')[0] + '+00:00',
        toDate: today.toISOString().split('.')[0] + '+00:00',
        quantity: 5,
        isAdminOrder: false,
        stock: 10,
    };

    await test.step('Create rule via API', async () => {
        await ShopAdmin.attemptsTo(CreateRule(testConfig));
    });

    await test.step('Validate rule via UI', async () => {
        // listing
        await ShopAdmin.goesTo(AdminRuleListing.url(uniqueId));
        await ShopAdmin.expects(AdminRuleListing.gridCell.getByText(testConfig.ruleName)).toBeVisible();
        await AdminRuleListing.gridCell.getByText(testConfig.ruleName).click();

        // general card
        await ShopAdmin.expects(AdminRuleDetail.header).toHaveText(testConfig.ruleName);
        await ShopAdmin.expects(AdminRuleDetail.nameInput).toHaveValue(testConfig.ruleName);
        await ShopAdmin.expects(AdminRuleDetail.priorityInput).toHaveValue(testConfig.rulePriority.toString());
        await ShopAdmin.expects(AdminRuleDetail.descriptionInput).toHaveValue(testConfig.ruleDescription);
        for (const ruleType of testConfig.ruleTypes) {
            await ShopAdmin.expects(AdminRuleDetail.typeItem.getByText(ruleType)).toBeVisible();
        }
        await ShopAdmin.expects(AdminRuleDetail.tagItem.getByText(testConfig.ruleTag)).toBeVisible();

        // conditions card
        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Total quantity of all products')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionLineItemGoodsTotalOperator).toHaveText('Is greater than / equal to');
        await ShopAdmin.expects(AdminRuleDetail.conditionLineItemGoodsTotalValue).toHaveValue(testConfig.quantity.toString());
        await AdminRuleDetail.conditionLineItemGoodsTotalFilter.click();
        await ShopAdmin.expects(AdminRuleDetail.conditionFilterModal).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Item available')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemInStockOperator).toHaveText('Is greater than / equal to');
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemInStockValue).toHaveValue(testConfig.stock.toString());
        await AdminRuleDetail.conditionFilterModalCloseButtonX.click();

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Date range')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeOperator).toHaveText('Excluding timestamp');
        await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeDateFieldFirst).toHaveValue((testConfig.fromDate.split('T')[0]).split('-').reverse().join('/'));
        await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeDateFieldSecond).toHaveValue((testConfig.toDate.split('T')[0]).split('-').reverse().join('/'));

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Customer surname')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionCustomerSurnameOperator).toHaveText('Is equal to');
        await ShopAdmin.expects(AdminRuleDetail.conditionCustomerSurnameValue).toHaveValue(testConfig.customerSurname);

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Item with tax rate')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationMatchOperator).toHaveText('At least one');
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationOperator).toHaveText('Is one of');
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationValue).toHaveText(testConfig.taxName);

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Time range')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionTimeRangeValueFirst).toHaveValue(testConfig.fromDate.split('T')[1].substring(0, 5));
        await ShopAdmin.expects(AdminRuleDetail.conditionTimeRangeValueSecond).toHaveValue(testConfig.toDate.split('T')[1].substring(0, 5));

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Order created by administrator (flow)')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionOrderCreatedByAdminValue).toHaveText(testConfig.isAdminOrder ? 'Yes' : 'No');
    });
});
