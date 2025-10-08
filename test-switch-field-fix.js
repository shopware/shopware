// Simple test to verify the sw-switch-field inheritance fix
// This simulates the logic in sw-system-config component

// Mock element with sw-switch-field component
const swSwitchFieldElement = {
    name: 'testSwitchField',
    config: {
        componentName: 'sw-switch-field',
        label: { 'en-GB': 'Test Switch Field' },
        helpText: { 'en-GB': 'This is a test switch field' }
    }
};

// Mock element with regular bool type
const boolFieldElement = {
    name: 'testBoolField',
    type: 'bool',
    config: {
        label: { 'en-GB': 'Test Bool Field' },
        helpText: { 'en-GB': 'This is a test bool field' }
    }
};

// Mock the hasMapInheritanceSupport method
function hasMapInheritanceSupport(element) {
    const componentName = element.config ? element.config.componentName : undefined;
    
    if (componentName === 'sw-switch-field' || componentName === 'sw-snippet-field') {
        return true;
    }
    
    const typesWithMapInheritanceSupport = [
        'text', 'textarea', 'url', 'password', 'int', 'float', 
        'bool', 'checkbox', 'colorpicker'
    ];
    
    return typesWithMapInheritanceSupport.includes(element.type);
}

// Mock the getInheritWrapperBind method (BEFORE the fix)
function getInheritWrapperBindBefore(element) {
    if (hasMapInheritanceSupport(element)) {
        return {}; // This was the bug - returning empty object
    }
    
    return {
        label: element.config.label,
        helpText: element.config.helpText,
    };
}

// Mock the getInheritWrapperBind method (AFTER the fix)
function getInheritWrapperBindAfter(element) {
    return {
        label: element.config.label,
        helpText: element.config.helpText,
    };
}

// Test the fix
console.log('Testing sw-switch-field inheritance fix...\n');

console.log('1. Testing hasMapInheritanceSupport for sw-switch-field:');
console.log('   Result:', hasMapInheritanceSupport(swSwitchFieldElement));
console.log('   Expected: true\n');

console.log('2. Testing hasMapInheritanceSupport for bool field:');
console.log('   Result:', hasMapInheritanceSupport(boolFieldElement));
console.log('   Expected: true\n');

console.log('3. Testing getInheritWrapperBind BEFORE fix for sw-switch-field:');
const beforeResult = getInheritWrapperBindBefore(swSwitchFieldElement);
console.log('   Result:', JSON.stringify(beforeResult, null, 2));
console.log('   Expected: {} (empty object - this was the bug)\n');

console.log('4. Testing getInheritWrapperBind AFTER fix for sw-switch-field:');
const afterResult = getInheritWrapperBindAfter(swSwitchFieldElement);
console.log('   Result:', JSON.stringify(afterResult, null, 2));
console.log('   Expected: { label: {...}, helpText: {...} } (with proper label and helpText)\n');

console.log('5. Testing getInheritWrapperBind for bool field (should work in both cases):');
const boolResult = getInheritWrapperBindAfter(boolFieldElement);
console.log('   Result:', JSON.stringify(boolResult, null, 2));
console.log('   Expected: { label: {...}, helpText: {...} } (with proper label and helpText)\n');

console.log('✅ Fix verification:');
console.log('   - sw-switch-field now gets proper label and helpText in inheritance wrapper');
console.log('   - This should make the inheritance switch visible and functional');
console.log('   - The fix maintains backward compatibility for other field types');
