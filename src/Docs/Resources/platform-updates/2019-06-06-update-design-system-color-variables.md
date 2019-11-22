[titleEn]: <>(Administration: Update design system color variables)

The color variables in the administration have been aligned with the design system colors.
The main color variables are now available in several gradations from light to dark.

For example the gray color now has those gradations:

```
// platform/src/Administration/Resources/app/administration/src/app/assets/scss/variables.scss

$color-gray-50: #F9FAFB; // Light
$color-gray-100: #F0F2F5;
$color-gray-200: #E0E6EB;
$color-gray-300: #D1D9E0;
$color-gray-400: #C2CCD6;
$color-gray-500: #B3BFCC; // Medium
$color-gray-600: #A3B3C2;
$color-gray-700: #94A6B8;
$color-gray-800: #8599AD;
$color-gray-900: #758CA3; // Dark
```

This should make it a lot easier to work with the design system in general because every design system color has it's counterpart in the administration variables. 

This also means that you can use the original colors from the design system directly and you don't have to deal with custom `lighten()` or `darken()` functions in your SCSS. In the past we did not have enough variables to cover all cases.

However there are deprecations to some variables. Those are inside a `@deprecated` category in the variables file. There are now less base colors in general - but with more gradations for each color. We have assigned the old variables with the new variables to be compatible with current modules or plugins. But we will migrate the old colors from now on.

For example:
```
$color-steam-cloud: $color-gray-300; // Old color #D8DDE6
```
Some colors have been slightly updated to achieve better contrast ratios.

**For new SCSS code please use the new gradation variables.**

If you want to take a look at the design system visit [shopware.design](https://shopware.design).
