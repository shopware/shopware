# Get involved

Shopware is available under dual license (AGPL v3 and proprietary license). If you want to contribute code (features or bugfixes), you have to create a pull request and include valid license information. You can either contribute your code under New BSD or MIT license.

If you want to contribute to the backend part of Shopware, and your changes affect or are based on ExtJS code, they must be licensed under GPL V3, as per license requirements from Sencha Inc.

If you are not sure which license to use, or want more details about available licensing or the contribution agreements we offer, you can contact us at <contact@shopware.com>.


# Pull Requests

When creating a pull requests you should mention:

 * *Why* you are changing it
 * *What* you are changing
 * If this will *break* something

Pull request should be English (title, description and code comments, if applicable).

When coding and committing, please:

 * Write your commit messages in English
 * Have them short and descriptive
 * Don't fix things which are related to other issues / pull requests
 * Mention your changes in the UPGRADE.md
 * Provide a test
 * Follow the coding standards


# Coding standards
All contributions should follow the [PSR-1](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md) and [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md) coding
standards.

To check for CS issues:

    composer cs-check

You can also fix reported errors automatically:

    composer cs-fix

If you use `composer cs-fix` to fix issues, make certain you add and commit any files changed!

# Start hacking

To start contributing, just fork the repository and clone your fork to your local machine:

    git clone git@github.com:[YOUR USERNAME]/shopware.git

After having done this, configure the upstream remote:

    cd shopware
    git remote add upstream git://github.com/shopware/shopware.git
    git config branch.master.remote upstream

To keep your master up to date:

    git checkout master
    git pull --rebase
    php composer.phar self-update
    php composer.phar install
    php bin/console sw:migrations:migrate
    php bin/console sw:snippets:to:db

Checkout a new topic-branch and you're ready to start hacking and contributing to Shopware:

    git checkout -b feature/your-cool-feature

If you're done hacking, filling bugs or building fancy new features, push your changes to your forked repo:

    git push origin feature/your-cool-feature


... and send us a pull request with your changes. We'll verify the pull request and merge it with the main branch.

# Running Tests

## Database
For most tests a configured database connection is required.

## Running the tests
The tests are located in the `tests/` directory
You can run the entire test suite with the following command:

    vendor/bin/phpunit -c tests

If you want to test a single component, add its path after the phpunit command, e.g.:

    vendor/bin/phpunit -c tests tests/Functional/Components/Api/

# Documentation

Developer documentation for Shopware is available [here](https://developers.shopware.com/). You can also contribute to the documentation project by submitting your pull requests to our [Devdocs Github project](https://github.com/shopware/devdocs)

# Translations

Shopware translations are done by the community and can be installed from the plugin store. If you wish to improve Shopware's translations, you can do so in our [Crowdin project page](https://crowdin.com/project/shopware).
