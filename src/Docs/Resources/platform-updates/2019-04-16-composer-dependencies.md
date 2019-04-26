[titleEn]: <>(Composer dependencies)

To ensure every bundle inside our mono-repository can be used standalone, their dependencies in the bundles `composer.json` must be maintained. Therefore we no longer update the platform's `composer.json` manually, except for metadata updates.

There is a new script ran as pre-commit hook, which collects every dependency of the bundles and merges them into the platforms `composer.json`. If the script notices any difference, you'll get a warning and have to review the changes:

> ERROR! The platform composer.json file has changed. Please review your commit and add the changes.
