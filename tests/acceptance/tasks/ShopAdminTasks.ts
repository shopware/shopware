import { mergeTests } from '@playwright/test';

/**
 * Media
 */
import { UploadImage } from './ShopAdmin/Product/UploadImage';

/**
 * Product
 */
import { GenerateVariants } from './ShopAdmin/Product/GenerateVariants';

/**
 * First Run Wizard
 */
import { FRWSalesChannelSelectionPossibility } from '@tasks/ShopAdmin/FRW/FRWSalesChannelSelectionPossibility';

/**
 * CustomField
 */
import { CreateCustomField } from '@tasks/ShopAdmin/CustomField/CreateCustomField';

/**
 * Add Landing Page From Category
 */
import { CreateLandingPage } from '@tasks/ShopAdmin/Category/CreateLandingPage';

/**
 * Customers
 */
import { CustomerGroupActivation } from '@tasks/ShopAdmin/Customers/CustomerGroupActivation';

/**
 * Orders
 */
import { AddCreditItem } from '@tasks/ShopAdmin/Orders/AddCreditItemViaAPI';
import { CreateInvoice } from '@tasks/ShopAdmin/Orders/CreateInvoiceViaAPI';

/**
 * Rules
 */
import { CreateRule } from '@tasks/ShopAdmin/RuleBuilder/CreateRule';
import { CreateRuleBillingCountry } from '@tasks/ShopAdmin/RuleBuilder/CreateRuleBillingCountry';

/**
 * Flows
 */
import { CreateFlowForValidation } from '@tasks/ShopAdmin/FlowBuilder/CreateFlowForValidation';

export const test = mergeTests(
    GenerateVariants,
    UploadImage,
    FRWSalesChannelSelectionPossibility,
    CreateCustomField,
    CreateLandingPage,
    CustomerGroupActivation,
    AddCreditItem,
    CreateInvoice,
    CreateRule,
    CreateRuleBillingCountry,
    CreateFlowForValidation,
);
