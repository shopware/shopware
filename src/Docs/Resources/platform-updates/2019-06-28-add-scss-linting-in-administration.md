[titleEn]: <>(Administration: Add SCSS linting in administration)

### Main usage
The SCSS files in the Administration are linted by Stylelint for a consistent code style. It triggers automatically
on a pre-commit for each edited SCSS file and shows errors.

The linter can also be started manually with PSH :
- check changed files (git diff): `./psh.phar administration:lint-scss`
- check changed files and fix errors automatically in files (git diff): `./psh.phar administration:lint-scss-fix`  

It is also possible to lint every file in the Administration. These commands shouldn't be used
on daily basis and therefore they are not included in the PSH commands. To run these 
commands you have to be in this folder `platform/src/Storefront/Resources/app/storefront/` and start the NPM 
commands directly.

- Lint all files: `npm run lint:scss-all`
- Lint and fix all files `npm run lint:scss-all:fix` (Warning: This changes data)

### PhpStorm Integration
You can show linting errors directly in PhpStorm. When you want to enable the live linting 
you have to open your Preferences and navigate to:
`Language & Frameworks` -> `Style Sheets` -> `Stylelint`. Here you have to enable the linting 
and change the path to your Stylelint Package  (`YOUR_PATH/development/platform/src/Storefront/Resources/app/storefront/node_modules/stylelint`).
Now you can see directly the errors in your SCSS files.