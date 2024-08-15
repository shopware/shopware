<div align="center">

[![Build Status](https://github.com/shopware/platform/workflows/PHPUnit/badge.svg)](https://github.com/shopware/platform/actions)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/shopware/platform/badges/quality-score.png)](https://scrutinizer-ci.com/g/shopware/platform/)
[![Latest Stable Version](https://poser.pugx.org/shopware/platform/v/stable)](https://packagist.org/packages/shopware/platform)
[![Total Downloads](https://poser.pugx.org/shopware/platform/downloads)](https://packagist.org/packages/shopware/platform)
[![Crowdin](https://badges.crowdin.net/shopware6/localized.svg)](https://translate.shopware.com/project/shopware6)
[![License](https://img.shields.io/github/license/shopware/platform.svg)](https://github.com/shopware/platform/blob/trunk/LICENSE)
[![GitHub closed pull requests](https://img.shields.io/github/issues-pr-closed/shopware/platform.svg)](https://github.com/shopware/platform/pulls)
[![Slack](https://img.shields.io/badge/chat-on%20slack-%23ECB22E)](https://slack.shopware.com?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

</div>


<p align="center"><a href="https://shopware.com" target="_blank" rel="noopener noreferrer"><img width="250" src="https://images.ctfassets.net/nqzs8zsepqpi/34zKqvPxTYtsQppJpgC9It/3b6901d9ba7082d5b4081d7171b268bf/composable-customer-experience-illustration.png"></a></p>

<h1 align="center">Shopware</h1>

<p align="center"><strong>Modern open source e-Commerce</strong>

[![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=Start%20your%20dev%20journey%20now!&url=https%3A%2F%2Fgithub.com%2Fshopware%2Fplatform&via=ShopwareDevs&hashtags=Shopware6,community)
</p>


Shopware 6 is an open headless commerce platform powered by [Symfony 7](https://symfony.com) and [Vue.js 3](https://vuejs.org) that is used by thousands of shops and supported by a huge, [worldwide community](https://slack.shopware.com) of developers, agencies and merchants.

If you like Shopware 6, give us a&nbsp;⭐️ &nbsp;on Github!

* 🙋‍♂️ &nbsp;[Be part of shopware!](https://www.shopware.com/en/jobs/) ‍&nbsp;We are hiring!  🙋
* 🌎 &nbsp;Discover our [website](https://www.shopware.com/en/)
* 🧩 &nbsp;Browse more than [2.000 apps](https://store.shopware.com) in our community store
* 📖 &nbsp;Learn how to [develop apps](https://developer.shopware.com/docs/) and everything else about the tech behind shopware
* 🉐 &nbsp;[Translate](https://translate.shopware.com) Shopware or help by contributing to existing languages
* 🛠 &nbsp;[Report bugs](https://github.com/shopware/shopware/issues) in our issue tracker
* 💡 &nbsp;Give us [feedback](https://feedback.shopware.com/) or vote existing ideas
* 👪 &nbsp;Exchange with more than 7.000 shopware developers in our [Slack community workspace](https://slack.shopware.com)

## Table of contents

- [Table of contents](#table-of-contents)
- [Project overview](#project-overview)
  - [Platform and Framework](#platform-and-framework)
- [Installation](#installation)
  - [Extending Shopware](#extending-shopware)
  - [Production setup](#production-setup)
  - [Code Contribution](#code-contribution)
    - [Contribution setup](#contribution-setup)
- [The Shopware CLA](#the-shopware-cla)
- [Authors \& Contributors](#authors--contributors)
- [License](#license)
- [Bugs \& Feedback](#bugs--feedback)
- [Reporting security issues](#reporting-security-issues)

## Project overview

To discover the features of Shopware and what sets us apart from other e-commerce systems, take the [feature tour](https://www.shopware.com/en/products/product-tour/) on the Shopware home page.

From a developer's perspective, here are some highlights that make Shopware easy and fun to work with:

### Platform and Framework

Shopware itself is based mainly on [Symfony](https://symfony.com/what-is-symfony) and [VueJS](https://vuejs.org/). It is a fully functional e-commerce platform, but it is also an **e-commerce framework**.

Shopware is:

- a ready-to-use [shopping cart system](https://docs.shopware.com/en/shopware-6-en/getting-started).
- a vendor dependency in your [flex project](https://developer.shopware.com/docs/guides/installation/template).
- [API-first](https://developer.shopware.com/docs/guides/integrations-api).
- [extensible through plugins](https://developer.shopware.com/docs/guides/plugins/plugins/plugin-base-guide):
  - Harness the full power of Symfony by creating bundles and loading them as part of the application.
- [extensible through apps](https://developer.shopware.com/docs/guides/plugins/apps/app-base-guide):
  - A modern, lightweight but powerful way to add functionality, requiring very little Shopware-specific knowledge.
- headless if you need it to be.

## Installation

### Extending Shopware

There are already a lot of extensions available in the [Shopware store](https://store.shopware.com/).

After setting up [Shopware locally for development](https://developer.shopware.com/docs/guides/installation), you can start with our extension guides in the documentation.

The preferred way of extending Shopware is through the [App System](https://developer.shopware.com/docs/guides/plugins/apps/app-base-guide). If the feature you want to implement needs direct access to the Shopware process and the database, you can also use the [plugin system](https://developer.shopware.com/docs/guides/plugins/plugins/plugin-base-guide).    
You can find an [overview and differentiation in the documentation](https://developer.shopware.com/docs/concepts/extensions).

### Production setup

The easiest way to run a Shopware shop is booking a commercial plan in the [Shopware cloud](https://www.shopware.com/en/shopware-cloud/), a fully managed setup, ready to use.

The recommended way for on-premise shops is installing Shopware [through the flex template](https://developer.shopware.com/docs/guides/installation/template). To unlock the full potential Shopware has to offer, [commercial plans](https://www.shopware.com/en/pricing/) are also available for on-premise.   
These plans enrich your shop with unique functionality, giving you an additional advantage over your competition.

There is a list of [hosting partners](https://www.shopware.com/en/partner/hosting/), who offer a pre-installed shop, making your start a lot faster.

We also provide a [web-based installer](https://www.shopware.com/en/download/), the [documentation](https://docs.shopware.com/en/shopware-6-en/first-steps/installing-shopware-6?category=shopware-6-en/getting-started) walks you through the necessary steps.

### Code Contribution

If you have decided to contribute code to Shopware and become a member of the Shopware community,
we appreciate your hard work and want to handle it with the most possible respect.
To ensure the quality of our code and our products we have created a guideline we all should endorse to.
It helps you and us to collaborate. Following these guidelines will help us to integrate your changes in our daily workflow.

Read more in [our contribution guideline](https://docs.shopware.com/en/shopware-platform-dev-en/contribution/contribution-guideline)
or in our short [HowTo contribute code](https://docs.shopware.com/en/shopware-platform-dev-en/contribution/contributing-code).

#### Contribution setup

There are multiple ways to get an installation running, the way with the fewest steps involved is using the contribute image from [dockware](https://dockware.io/), a community maintained docker setup by the Shopware agency [dasistweb](https://www.dasistweb.de/en/). More on this in the [documentation](https://developer.shopware.com/docs/guides/installation/community/dockware.html).

## The Shopware CLA

When submitting your code to Shopware you automatically need to sign our CLA (Contributor License Agreement).
This CLA ensures that Shopware will stay an open and living product.
In short, you give the explicit right to use your code in Shopware to shopware AG.

## Authors & Contributors

Shopware is built with the help of our community.

You can find an overview of everyone who contributed to the platform repository in the [official github overview](https://github.com/shopware/platform/graphs/contributors).
Additionally there are numerous people contributing to the ecosystem through activities not related to the codebase. Thank you all for being part of this!

## License

Shopware 6 is completely free and released under the [MIT License](LICENSE).

## Bugs & Feedback

No software is perfect, Shopware is no exception. Should you spot a bug, please report it in our [issue tracker](https://github.com/shopware/shopware/issues).

If you want to suggest features or how certain parts of Shopware 6 work, we'd be happy to [hear from you](https://feedback.shopware.com/).

## Reporting security issues

Please have a look at our [security policy](SECURITY.md).
