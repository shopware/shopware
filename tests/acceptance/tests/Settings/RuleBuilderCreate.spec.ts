import { test } from '@fixtures/AcceptanceTest';

test('As an admin user, I want to create a rule', { tag: '@Rule' }, async ({
    AdminRuleDetail,
    AdminRuleListing,
    ShopAdmin,
    IdProvider,
    TestDataService,
    CreateRule,

}) => {

    const { uuid: ruleUuId, id: uniqueId } = IdProvider.getIdPair();
    const {id: taxId, name: taxName} = (await TestDataService.createTaxRate());
    const ruleTag = (await TestDataService.createTag(`Test tag - ${uniqueId}`)).name;
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    yesterday.setHours(yesterday.getHours() + 1);
    const fromDate = today.toISOString().split('.')[0] + '+00:00';
    const toDate = yesterday.toISOString().split('.')[0] + '+00:00';

    const ruleData = {
        ruleId: ruleUuId,
        ruleName: `Test rule - ${uniqueId}`,
        ruleTypes: ['Price', 'Shipping', 'Payment', 'Flow Builder'],
        rulePriority: 1,
        ruleDescription: 'This is a test rule, created to test the Rule Builder.',
        ruleTag: ruleTag,
        taxId: taxId,
        taxName: taxName,
        customerSurname: 'Schmitz-Rimpler',
        fromDate: fromDate,
        toDate: toDate,
        quantity: 5,
        adminOrder: false,
        inStock: 10,
    };

    await test.step('Create rule via API', async () => {
        await ShopAdmin.attemptsTo(CreateRule(ruleData));
    });

    await test.step('Validate rule via UI', async () => {

        // listing
        await ShopAdmin.goesTo(AdminRuleListing.url());
        await ShopAdmin.expects(AdminRuleListing.gridCell.getByText(ruleData.ruleName)).toBeVisible();
        await AdminRuleListing.gridCell.getByText(ruleData.ruleName).click();

        // general card
        await ShopAdmin.expects(AdminRuleDetail.smartBarHeader).toHaveText(ruleData.ruleName);
        await ShopAdmin.expects(AdminRuleDetail.nameInput).toHaveValue(ruleData.ruleName);
        await ShopAdmin.expects(AdminRuleDetail.priorityInput).toHaveValue(ruleData.rulePriority.toString());
        await ShopAdmin.expects(AdminRuleDetail.descriptionInput).toHaveValue(ruleData.ruleDescription);
        for (const ruleType of ruleData.ruleTypes) {
            await ShopAdmin.expects(AdminRuleDetail.typeItem.getByText(ruleType)).toBeVisible();
        }
        await ShopAdmin.expects(AdminRuleDetail.tagItem.getByText(ruleTag)).toBeVisible();

        // conditions card
        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Total quantity of all products')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionLineItemGoodsTotalOperator).toHaveText('Is greater than / equal to');
        await ShopAdmin.expects(AdminRuleDetail.conditionLineItemGoodsTotalValue).toHaveValue(ruleData.quantity.toString());
        await AdminRuleDetail.conditionLineItemGoodsTotalFilter.click();
        await ShopAdmin.expects(AdminRuleDetail.conditionFilterModal).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Item available')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemInStockOperator).toHaveText('Is greater than / equal to');
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemInStockValue).toHaveValue(ruleData.inStock.toString());
        await AdminRuleDetail.conditionFilterModalCloseButtonX.click();

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Date range')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeOperator).toHaveText('Excluding timestamp');
        await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeDateFieldFirst).toHaveValue(fromDate.split('+')[0]);
        await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeDateFieldSecond).toHaveValue(toDate.split('+')[0]);

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Customer surname')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionCustomerSurnameOperator).toHaveText('Is equal to');
        await ShopAdmin.expects(AdminRuleDetail.conditionCustomerSurnameValue).toHaveValue(ruleData.customerSurname);

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Item with tax rate')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationMatchOperator).toHaveText('At least one');
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationOperator).toHaveText('Is one of');
        await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationValue).toHaveText(taxName);

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Time range')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionTimeRangeValueFirst).toHaveValue(fromDate.split('T')[1].substring(0, 5));
        await ShopAdmin.expects(AdminRuleDetail.conditionTimeRangeValueSecond).toHaveValue(toDate.split('T')[1].substring(0, 5));

        await ShopAdmin.expects(AdminRuleDetail.conditionSelectField.getByText('Order created by administrator (flow)')).toBeVisible();
        await ShopAdmin.expects(AdminRuleDetail.conditionOrderCreatedByAdminValue).toHaveText(ruleData.adminOrder ? 'Yes' : 'No');
    });
});
