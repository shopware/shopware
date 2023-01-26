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


Shopware 6 is an open headless commerce platform powered by [Symfony 5.4](https://symfony.com) and [Vue.js 2.6](https://vuejs.org) that is used by thousands of shops and supported by a huge worldwide community of developers, agencies and merchants.

If you like Shopware 6, give us a&nbsp;⭐️ &nbsp;on Github

* 🙋‍♂️ &nbsp;[Be part of shopware!](https://www.shopware.com/en/jobs/) ‍&nbsp;We are hiring!  🙋
* 🌎 &nbsp;Discover our [website](https://www.shopware.com/en/)
* 🧩 &nbsp;Browse more than [2.000 apps](https://store.shopware.com) that are already available
* 📖 &nbsp;Learn how to [develop apps](https://developer.shopware.com/docs/) and everything else about the tech behind shopware
* 🉐 &nbsp;[Translate](https://translate.shopware.com) Shopware or help by contributing to existing languages
* 👍 &nbsp;Follow us on [Twitter](https://twitter.com/shopwaredevs) to get updates
* 🛠 &nbsp;[Report bugs or add feature ideas](https://issues.shopware.com) in our issue tracker
* 🗨 &nbsp;Help and get helped in our - [Community forum](https://forum.shopware.com/)
* 👪  &nbsp;Exchange with more than 5.000 shopware developers in our  - [Slack](https://slack.shopware.com)
* 🕹 &nbsp;[Download Shopware](https://www.shopware.com/de/download/) or start playing with [Dockware](https://github.com/dockware/dockware)

## Table of contents

- [Open Commerce Platform](#open-commerce-platform)
- [Business Model Composer](#business-model-composer)
- [Composable Customer Experience](#composable-customer-experience)
- [Under the hood](#under-the-hood)
- [Extension-system](#extension-system)
- [Demo](#Demo)
- [Technology](#Technology)
- [Repository structure](#shopware-6-repository-structure)
- [Installation](#quickstart--installation)
- [Roadmap](#roadmap)
- [Community](#community)
- [Ecosystem](#ecosystem)
- [Contribution](#contribution)
- [License](#license)
- [Authors](#authors)

## Open Commerce platform
**We are always open,
you are always free**

With Shopware you control your destiny. Always having access to the source, you own it. No lock-ins, no compromises, no limits.
**Your freedom to grow.**

Open commerce empowers you to seize the collective force of our community and partnerships. You benefit from a worldwide network of established developers, looking into the future of ecommerce and continuously advancing the platform in order to meet the latest market developments. It is exactly this focus that makes the difference and enables our merchants to grow strongly and sustainably in the ever-evolving world of digital commerce.

<img src='https://images.ctfassets.net/nqzs8zsepqpi/6L9YIBLLZ5cOdUSDmgcER/234366961af834327d17621c26ede892/open-commerce-platform-mood-image.png'></td>




<img src='https://assets.shopware.com/media/icons/marketing/blue/headless-api-first.svg' width='32' align='left'>&nbsp;
**Innovative and future-oriented: Open, headless, API-first, cloud & self-managed**<br /><br />
<img src='https://assets.shopware.com/media/icons/marketing/blue/seamless-integration-system-landscape.svg' width='32' align='left'>&nbsp;
**Seamless integration into your system landscape**<br /><br />
<img src='https://assets.shopware.com/media/icons/marketing/blue/open-established-technologies.svg' width='32' align='left'>&nbsp;
**Established technologies such as Symfony and vue.js**

---

## Business Model Composer
### Plug and play your business model:

<img src='https://images.ctfassets.net/nqzs8zsepqpi/nmbB4kx2XgV9gIJtkVWI6/6c3f14eb022b54d980a10be703fd4710/business-model-composer-illustration.png'>

<p>
Adapt fast and test various business models on-time: Digital events, subscription services, consultations, and highly customisable goods – whether to buy or to rent. There are no limits to the power of human imagination. Dare to dream, we'll make it happen!
</p>


<img src='https://images.ctfassets.net/nqzs8zsepqpi/2fzpK9THpmhh285950bwPl/8d018b21e1173cafb767a8120e2d9b8b/flowbuilder.svg'>


#### The Flow-builder makes the implementation of business processes easy


<img src='https://images.ctfassets.net/nqzs8zsepqpi/5WUwOmbBzXKujLraK02eAp/a0e208a7df0d5517df419be279578dc0/flow-builder-intro-illustration.svg'>

<img src='https://assets.shopware.com/media/icons/marketing/blue/business-models-on-time.svg' width='32' align='left'>&nbsp;
**Run different business models on-time with one solution** <br />

<img src='https://assets.shopware.com/media/icons/marketing/blue/unique-business-flow.svg' width='32' align='left'>&nbsp;
**Compose your unique business flow with no-code/low-code**<br />

<img src='https://assets.shopware.com/media/icons/marketing/blue/rules-and-pioneer.svg' width='32' align='left'>&nbsp;
**Set your own rules and pioneer into new territory**<br />


## Composable Customer Experience

<p align="center"><img src='https://images.ctfassets.net/nqzs8zsepqpi/2mI5yTktojiFqNsB66pCsA/752bbd8eddb1b2d587d43d4cec09ffa7/composable-customer-experience-illustration.png' width=500 align='center'></p>

<br />

### Engage your customers across all channels with the perfect harmony of content and commerce

<br />

[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/shoppingexp.gif)](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/shoppingexp.gif)

[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/cms.gif)](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/cms.gif)

**Designing content is fast and intuitive with the Shopping Experiences.**


<img src='https://assets.shopware.com/media/icons/marketing/blue/seamless-integration-content-and-commerce.svg' width='32' align='left'>&nbsp;
**Strengthen your brand ID with our flawless integration of content and commerce** <br />

<img src='https://assets.shopware.com/media/icons/marketing/blue/touchpoints-into-trust-points.svg' width='32' align='left'>&nbsp;
**Convert casual touch-points into fundamental trust-points**<br />

<img src='https://assets.shopware.com/media/icons/marketing/blue/pwa.svg' width='32' align='left'>&nbsp;
**Fly high and create the next-level user experience with Shopware's PWA**<br />

## Under the hood


The build-in **Shopware 6 Storefront** is based on [Twig](https://twig.symfony.com/doc/2.x/templates.html)
and [Bootstrap](https://getbootstrap.com/docs/4.3/getting-started/introduction/).
Two well known and easy to learn frameworks, making the creation of templates a breeze and bringing many advantages for merchants & developers:

* Lightweight and fast storefront
* Works perfectly on all end devices
* Reduced costs & effort required to make customisations, thanks to being built upon basic code & development standards that can be easily and widely used
* Flexible basis for creating your own custom themes
* Clear product presentation – filter according to properties and variants
* Numerous sorting options for product lists in the storefront
* Customer ratings can be handled conveniently in the administration and easily published in the storefront


[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/storefrontT.png)](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/storefront.png)

Together with our friends of [VueStorefront](https://github.com/vuestorefront) we also provide an open source PWA frontend called [**"Shopware PWA"**](https://github.com/vuestorefront/shopware-pwa):<br />

**What makes Shopware PWA unique:**

* Quick integration & drop-in replacement for standard storefront
* Unrestricted creativity for frontend developers
* Modern tech-stack
* Made for enterprise-level complexity

<div align="center">
<img align='center' src='https://images.ctfassets.net/nqzs8zsepqpi/6raZzvIPlRGUhSZGMpKg4F/c6f3d96d223c886f0134baf5069bea80/shopware-pwa-framework.svg'>
</div>


----

The **Shopware 6 Administration** is based on [Vue.js](https://vuejs.org/v2/guide/) and [twig.js](https://github.com/twigjs/twig.js/wiki),
making the creation of new modules fast and easy.
Get started with the [design documentation](https://shopware.design/).

[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/rulebuilder.gif)](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/rulebuilder.gif)

## Extension-system

Shopware 6 is a lean and extremely flexible product that can be easily adapted to meet your requirements – and the Plugin Manager is your command centre for managing the apps and themes added to your shop. Using this module, you can install, purchase, update or delete apps.

<img src='https://images.ctfassets.net/nqzs8zsepqpi/37KEQR5sgxpBlar7Q5PyGL/6f638937957a6eefeca74af83b28d5ba/EN-Plugin_Manager_Updates-190520.png'>
<br />
<br />
<p align="center">
<img src='https://ecommerce.shopware.com/hubfs/21-T1-App-Development-Keyvisual.svg' width=250 align="center">

You can *easily* build your own apps and extensions by following our [developer documentation](https://developer.shopware.com/docs/guides/plugins)

</p>
<br />

<br />


---

## Demo

You can easily setup shopware by:

* [Download](https://www.shopware.com/en/download/#shopware-6) shopware and run it on your server
* using [Dockware](https://dockware.io/)
* or simply setup a [cloud shop](https://www.shopware.com/en/download/#create-onlineshop) and tryout everything

## Technology

Shopware 6 uses Symfony as the standard framework, while the administration is completely based on Vue.js. In relying on technological standards, we’ve made it even easier to work with Shopware – while reducing dependency on specialised knowledge. Our goal is to make it as comfortable as possible to get started with Shopware by providing you with various resources, completely free of charge.

### API First
We want commerce to take place where people are; independent of place, time and end device. Following to the API-first approach, Shopware 6 provides retailers with the technological foundation to effortlessly build retail strategies across channels and devices.

![The core architecture](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/platformcontext.svg)

The chart shows how the Shopware Platform fits into your enterprise.
It provides web frontends for management and for commerce through a multitude of sales channels.
It comes with a set of user facing interfaces and provides the ability to connect to your own infrastructure and outside services through REST-APIs.

More information can be found [in the documentation](https://developer.shopware.com/docs/concepts/api).

## Shopware 6 repository structure

Shopware 6 consists of multiple repositories, two of them are important to you:

- `shopware/platform` is a [mono repository](https://www.atlassian.com/git/tutorials/monorepos)
    - This is where the shopware core is developed. You need it as dependency in your projects
    - This is where you can participate in the development of Shopware through pull requests
    - It's split into multiple repositories for production setups, all read-only
- A template based on Symfony flex, which you can use to start your own project
    - **This is where your journey with shopware starts**
    - Installation see below!

## Quickstart / Installation

A full installation guide covering different dev environments is available in the [docs](https://developer.shopware.com/docs/guides/installation).

*For the impatient reader, here is a tl;dr using docker and symfony cli.*

Let's start by creating a new project:

```bash
> composer create-project shopware/production:dev-flex project
```

You now have the application template for the Shopware Platform in the directory `project`, we now change into it:

```bash
> cd project
```

Now we start our service containers:

```bash
> docker compose up -d
```

And install Shopware with the following command:

```bash
symfony console system:install --basic-setup --drop-database --create-database -f
```

Start the webserver:

```bash
symfony server:start -d
```

To be sure that the installation succeeded, just open the following URL in your favorite browser: [localhost:8000](http://localhost:8000/)

[Now you're all set to start developing your first plugin.](https://developer.shopware.com/docs/guides/plugins/plugins/plugin-base-guide)

## Roadmap

### You make the roadmap!

Shopware 6 will continue to evolve together with you and your feedback. This is our number one priority!

With openness as one of our core values, we will always provide you with a transparent overview of our product development.
The Shopware Roadmap shows you what we are working on, what we want to tackle next, and what visions we have for the future.

[Take a look at the current roadmap here.](https://shopware.com/en/roadmap/)

## Our community is our strongest asset

In today’s information-based world, you cannot thrive in closed systems. Black boxes and vendor lock-in models hurt innovation – and belong in the dark ages of ecommerce.

The future of IT is all about collaboration. At Shopware, we believe that the best ecommerce solution can only be developed in constant exchange with the people that use it every day. This is why we made a clear promise to the open source approach and embrace everyone willing to participate. We consider our community to be our greatest strength; not our competitor, like many companies tend to do.

We believe that our open source edition is our strongest asset and that we need, now more than ever, to invest in our ecosystem of partners and developers. So that we can work together to collaborate across backgrounds, experiences and ideas and mutually benefit from the software that results.

**Join the community now** 🖤

- **Discuss:** [forum.shopware.com](https://forum.shopware.com/categories/international)
- **Slack:** [slack.shopware.com](https://slack.shopware.com)
- **Follow us on Twitter:** [@ShopwareDevs](https://twitter.com/ShopwareDevs)

Subscribe to our **[developer newsletter](https://www.shopware.com/en/community/developers/#newsletter)** and get updates about:

- Releases
- Upcoming breaking changes
- Important documentation changes and updates
- Community events
- Relevant blog articles

[Subscribe now](https://www.shopware.com/en/community/developers/#newsletter)

### Give us feedback

<table>
  <tr>
    <td width="250"><img src="https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/mnaczenski.png" /></td>
    <td>
      <strong>Moritz Naczenski</strong><br>
      Community Manager<br>
      Twitter: <a href="https://twitter.com/m_naczenski">@m_naczenski</a>
    </td>
    <td width="250"><img src="https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/ndzoesch.png" /></td>
    <td>
      <strong>Niklas Dzösch</strong><br>
      Developer Evangelist<br>
      <a href="mailto:developer@shopware.com">developer@shopware.com</a><br>
      Twitter: <a href="https://twitter.com/ndzoesch">@ndzoesch</a>
      </td>
    </tr>
</table>

## Ecosystem

Our Shopware Ecosystem gives you all the information you need to dive deep into the Shopware universe.

### Shopware Community Store

Whether plugin, theme or marketing tool: You can easily extend the functionality of your shop with over 1,500 available apps and extensions in the Community Store.

[store.shopware.com](https://store.shopware.com)

### Academy

Do you want to become a Shopware expert or get a sneak peek into the software? Find a training session that is individually tailored to your interests.

[shopware.com/academy/](https://www.shopware.com/academy/)

## Contribution

First of all - Every contribution is meaningful, so thank you for participating.

You want to participate in the development of Shopware? There are many ways to contribute:

-   Submitting pull requests
-   Reporting issues on the [issue tracker](https://issues.shopware.com/)
-   Discuss shopware on e.g [Slack](https://slack.shopware.com) or our [forum](https://forum.shopware.com/categories/shopware-6)
- Write a translation for shopware on [crowdin](https://translate.shopware.com)

You have a question regarding contribution, or you want to contribute in another way?

Please write us an email: contributors@shopware.com

### Installation of Shopware for contributors

The installation process is slightly different for working on the platform. It's not necessary to create a new project and install the platform as a dependency. Instead, we will work on it directly.

Let's start by cloning the platform repository

```bash
> git clone git@github.com:shopware/platform.git shopware-platform
```

Change directory into our newly cloned project:

```bash
> cd shopware-platform
```

Install the Composer dependencies

```bash
> composer update
```

Now we start our service containers:

```bash
> docker compose up -d
```

Now, set up the Shopware environment with the following command:

```bash
> ./bin/console system:setup
```

URL to your /public folder should be: `http://localhost:8000`

For the database details, use the following:

Host: 127.0.0.1
User: root
Password: root
Database: shopware

At the end, the system will create a `.env` file containing the provided configuration.

Now we install Shopware with:

```bash
> composer setup
```

This will run the migrations, install dependencies and build the assets.

Start the webserver:

```bash
symfony server:start -d
```

To be sure that the installation succeeded, just open the following URL in your favorite browser: [localhost:8000](http://localhost:8000/)

### Code Contribution

If you have decided to contribute code to Shopware and become a member of the Shopware community,
we appreciate your hard work and want to handle it with the most possible respect.
To ensure the quality of our code and our products we have created a guideline we all should endorse to.
It helps you and us to collaborate. Following these guidelines will help us to integrate your changes in our daily workflow.

Read more in [our contribution guideline](https://docs.shopware.com/en/shopware-platform-dev-en/contribution/contribution-guideline)
or in our short [HowTo contribute code](https://docs.shopware.com/en/shopware-platform-dev-en/contribution/contributing-code).

### The Shopware CLA

When submitting your code to Shopware you automatically need to sign our CLA (Contributor License Agreement).
This CLA ensures that Shopware will stay an open and living product.
In short, you give the explicit right to use your code in Shopware to shopware AG.

## Reporting security issues
Please have a look at our [security policy](SECURITY.md).

## License

Shopware 6 is completely free and released under the [MIT License](LICENSE).

## Authors

[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/OliverSkroblin.png)](https://github.com/OliverSkroblin)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/janbuecker.png)](https://github.com/janbuecker)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/klarstil.png)](https://github.com/klarstil)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/tobiasberge.png)](https://github.com/tobiasberge)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/jenskueper.png)](https://github.com/jenskueper)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/SebastianFranze.png)](https://github.com/SebastianFranze)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/leichteckig.png)](https://github.com/leichteckig)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/mitelg.png)](https://github.com/mitelg)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/Phil23.png)](https://github.com/Phil23)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/keulinho.png)](https://github.com/keulinho)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/benjamin-ott.png)](https://github.com/benjamin-ott)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/JanPietrzyk.png)](https://github.com/JanPietrzyk)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/taltholtmann.png)](https://github.com/taltholtmann)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/arnoldstoba.png)](https://github.com/arnoldstoba)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/HoelShare.png)](https://github.com/HoelShare)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/emmer91.png)](https://github.com/emmer91)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/ssltg.png)](https://github.com/ssltg)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/marcelbrode.png)](https://github.com/marcelbrode)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/pantrtxp.png)](https://github.com/pantrtxp)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/Staff-d.png)](https://github.com/Staff-d)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/PaddyS.png)](https://github.com/PaddyS)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/Christian-Rades.png)](https://github.com/Christian-Rades)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/azoffmann.png)](https://github.com/azoffmann)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/seggewiss.png)](https://github.com/seggewiss)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/lukasrump.png)](https://github.com/lukasrump)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/renebitter.png)](https://github.com/renebitter)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/swDennis.png)](https://github.com/swDennis)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/uehler.png)](https://github.com/uehler)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/htkassner.png)](https://github.com/htkassner)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/rsenf.png)](https://github.com/rsenf)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/GitEvil.png)](https://github.com/GitEvil)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/kevinrudde.png)](https://github.com/kevinrudde)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/Sironheart.png)](https://github.com/Sironheart)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/jennifer-utz.png)](https://github.com/jennifer-utz)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/oktupol.png)](https://github.com/oktupol)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/svenfinke.png)](https://github.com/svenfinke)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/florianklockenkemper.png)](https://github.com/florianklockenkemper)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/teiling88.png)](https://github.com/teiling88)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/jeboehm.png)](https://github.com/jeboehm)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/elkmod.png)](https://github.com/elkmod)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/Crease29.png)](https://github.com/Crease29)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/EtienneBruines.png)](https://github.com/EtienneBruines)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/Haehnchen.png)](https://github.com/Haehnchen)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/King-of-Babylon.png)](https://github.com/King-of-Babylon)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/niklasbuechner.png)](https://github.com/niklasbuechner)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/svenmuennich.png)](https://github.com/svenmuennich)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/screeny05.png)](https://github.com/screeny05)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/fabianhueske.png)](https://github.com/fabianhueske)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/hanneswernery.png)](https://github.com/hanneswernery)
[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/avatars/hlohaus.png)](https://github.com/hlohaus)
