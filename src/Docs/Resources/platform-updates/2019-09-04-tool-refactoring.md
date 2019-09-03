[titleEn]: <>(tool refactoring)

The tooling, e.g. used for static analysis and code style fixing, has been refactored.
The phar files are no longer part of the platform repository.
Instead they are separated composer dependencies in `development/dev-ops/analyze`.
To prevent cross dependencies between shopware and the different tools, we use a composer plugin,
which symlinks the required binaries to the default composer vendor directory.
But each tool has its own composer.json file, so there will be no conflict,
if one tool uses this Symfony version and the other tool another Symfony version.

### Changes

- The `psh` command `phpstan` has been renamed to `static-analyze`
  - It no longer executes [PHPStan](https://github.com/phpstan/phpstan) only, but also [Psalm](https://github.com/vimeo/psalm)
  - One could say Psalm does the same as PHPStan, but in fact Psalm recognizes things which PHPStan doesn't and vice versa
- The `platform/bin` directory has been removed completely
  - The code for the pre-commit hook could now be found in the `development/dev-ops` directory
  - The tools are located in `development/dev-ops/analyze`
  - The config files for the different tools remain in the platform repository
- If you have relied on the phar files in the `platform/bin` directory, e.g. in your plugin, use the binaries from `development/dev-ops/analyze/vendor/bin` now
  - Assuming you are in the development root directory:
  - Old: ~~php vendor/shopware/platform/bin/php-cs-fixer.phar fix~~
  - New: php dev-ops/analyze/vendor/bin/php-cs-fixer fix
- The pre-commit hook location has been changed, so you need to set it new, which should be done automatically with `composer update`

### Additions

- Psalm has been added as static analysis tool
- [PHPStan PHPUnit plugin](https://github.com/phpstan/phpstan-phpunit) has been added
  - If you check with `assertNotNull` you do not need to check again by yourself, if the variable is null for the further code
  - Mock support

### Update path

- `git pull` both development and platform
- `composer update` should set the pre-commit
- `./psh.phar init-composer` initialises also the analysis tools
