# Memory Leak Fix Testing Guide

## Changes Made

### 1. Store Cleanup Implementation
- Added comprehensive cleanup in `beforeRouteLeave` and `beforeDestroy` hooks
- Implemented `cleanupProductDetailData()` method that resets:
  - `swProductDetail` store (main product data)
  - `shopwareApps.selectedIds` 
  - CMS page state
  - Error store API errors
  - SEO URL store (if exists)
  - Pending SEO promises

### 2. Memory Usage Optimizations
- Added limits to product associations to prevent loading excessive data:
  - Media: Limited to 100 items per product
  - Tags: Limited to 100 items
  - Categories: Limited to 200 items
  - Cross-selling groups: Limited to 20 groups with 50 products each
  - Product reviews: Limited to 50 items
  - Various other associations limited appropriately

## Testing Instructions

### Prerequisites
1. Set up a Shopware instance with the demo data:
   ```bash
   composer require shopware/dev-tools
   bin/console framework:demodata --products 5000 --rules 200 --customers 500 --categories 200
   ```

### Memory Leak Test Procedure

#### Before Fix (Baseline)
1. Open Chrome browser in a VM/container with limited memory (4GB recommended)
2. Open Chrome Task Manager (Menu > More tools > Task manager)
3. Navigate to Shopware administration and log in
4. Go to Catalogue > Products
5. Open products one by one (at least 20-30 products)
6. Navigate back to product list between each product
7. Monitor memory usage in Chrome Task Manager
8. Note the continuous memory growth

#### After Fix (Verification)
1. Apply the changes from this fix
2. Clear browser cache and restart browser
3. Repeat the same test procedure
4. Memory usage should:
   - Stabilize after initial loading
   - Not continuously grow when navigating between products
   - Show periodic drops when garbage collection occurs
   - Remain significantly lower than the baseline test

### Expected Results

#### Memory Usage Patterns
- **Before Fix**: Continuous memory growth, eventual browser tab crash
- **After Fix**: Stable memory usage with periodic cleanup

#### Performance Improvements
- Faster navigation between products
- Reduced browser memory consumption
- No more tab crashes due to memory exhaustion
- Better overall administration performance

### Monitoring Points

1. **Chrome Task Manager**: Monitor "Memory" column for the administration tab
2. **Browser DevTools**: Use Memory tab to take heap snapshots
3. **System Memory**: Monitor overall system memory usage
4. **Browser Responsiveness**: Check for lag or freezing

### Additional Testing

#### Stress Test
1. Open 50+ products in rapid succession
2. Navigate between different product tabs (General, Specifications, etc.)
3. Edit product data and save
4. Check memory usage remains stable

#### Long Session Test
1. Keep administration open for extended period (2+ hours)
2. Perform normal product management tasks
3. Memory should not continuously grow over time

## Technical Details

### Root Cause
The memory leak was caused by:
1. Product detail store (`swProductDetail`) not being reset when navigating away
2. Accumulated product data, associations, and related entities
3. Heavy data loading without cleanup
4. Missing lifecycle cleanup hooks

### Solution Components
1. **Store Reset**: Proper cleanup of Pinia stores using `$reset()`
2. **Lifecycle Hooks**: Both route-based and component-based cleanup
3. **Data Limits**: Reasonable limits on association loading
4. **Comprehensive Cleanup**: Multiple store cleanup to prevent any data accumulation

### Files Modified
- `src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-detail/index.js`

### Backward Compatibility
- All changes are backward compatible
- No breaking changes to existing functionality
- Association limits are generous enough to not affect normal usage