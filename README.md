<div align="center">

[![Build Status](https://github.com/shopware/platform/workflows/PHPUnit/badge.svg)](https://github.com/shopware/platform/actions)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/shopware/platform/badges/quality-score.png)](https://scrutinizer-ci.com/g/shopware/platform/)
[![Latest Stable Version](https://poser.pugx.org/shopware/platform/v/stable)](https://packagist.org/packages/shopware/platform)
[![Total Downloads](https://poser.pugx.org/shopware/platform/downloads)](https://packagist.org/packages/shopware/platform)
[![License](https://img.shields.io/github/license/shopware/platform.svg)](https://github.com/shopware/platform/blob/master/license.txt)
[![GitHub closed pull requests](https://img.shields.io/github/issues-pr-closed/shopware/platform.svg)](https://github.com/shopware/platform/pulls)
[![Slack](https://img.shields.io/badge/chat-on%20slack-%23ECB22E)](https://slack.shopware.com?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)
[![Development Template](https://img.shields.io/badge/start%20with-shopware%2Fdevelopment-blue.svg)](https://github.com/shopware/development)

</div>


<p align="center"><a href="https://shopware.com" target="_blank" rel="noopener noreferrer"><img width="250" src="https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/swlogo_x250.png"></a></p>

<h1 align="center">Shopware 6</h1>

<p align="center"><strong>Realize your ideas - fast and without friction.</strong>

[![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=Start%20your%20dev%20journey%20now!&url=https%3A%2F%2Fgithub.com%2Fshopware%2Fplatform&via=ShopwareDevs&hashtags=Shopware6,community)
</p>

Shopware 6 is an open source ecommerce platform based on a quite modern technology stack that is powered by [Symfony](https://symfony.com) and [Vue.js](https://vuejs.org).
It's the successor of the very successful ecommerce shopping cart [Shopware 5](https://github.com/shopware/shopware) which has over 800,000 downloads.
Shopware 6 is focused on an API-first approach, so it's quite easy to think in different sales channels and make ecommerce happen whereever you want it.

If you like Shopware 6, give us a star on Github ★

- **Read the docs**: [https://docs.shopware.com/](https://docs.shopware.com/)
- **Start developing**: [https://github.com/shopware/development](https://github.com/shopware/development)
- **File an issue**: [https://issues.shopware.com](https://issues.shopware.com)

## Table of contents

- [Take a glimpse](#take-a-glimpse)
- [Technology](#technology)
- [Repository structure](#shopware-6-repository-structure)
- [Installation](#quickstart--installation)
- [Roadmap](#roadmap)
- [Community](#community)
- [Ecosystem](#ecosystem)
- [Contribution](#contribution)
- [License](#license)
- [Authors](#authors)

## Take a glimpse

The **Shopware 6 Storefront** is based on [Twig](https://twig.symfony.com/doc/2.x/templates.html)
and [Bootstrap](https://getbootstrap.com/docs/4.3/getting-started/introduction/).
Two well known and easy to learn frameworks, making the creation of templates a breeze! 

[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/storefrontT.png)](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/storefront.png)

The **Shopware 6 Administration** is based on [Vue.js](https://vuejs.org/v2/guide/) and [twig.js](https://github.com/twigjs/twig.js/wiki),
making the creation of new modules fast and easy.
Get started with the [design documentation](https://shopware.design/).   

[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/rulebuilder.gif)](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/rulebuilder.gif)

The Rulebuilder makes the implementation of business processes easy.

---

[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/shoppingexp.gif)](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/shoppingexp.gif)

[![](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/cms.gif)](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/cms.gif)

Designing content is fast and intuitive with the Shopping Experiences.

---

## Technology

Shopware 6 provides Services through REST-APIs and rich user interfaces to customers and administrators alike.

![The core architecture](https://s3.eu-central-1.amazonaws.com/shopware-platform-assets/github-platform/readme/platformcontext.svg)

The chart shows how the Shopware Platform fits into your enterprise.
It provides web frontends for management and for commerce through a multitude of sales channels.
It comes with a set of user facing interfaces and provides the ability to connect to your own infrastructure and outside services through REST-APIs.

More information can be found [in the documentation](https://docs.shopware.com/en/shopware-platform-dev-en/internals).

## Shopware 6 repository structure

Shopware 6 consists of multiple repositories, two of them are important to you:

- `shopware/platform` is a [mono repository](https://www.atlassian.com/git/tutorials/monorepos)
  - This is where the shopware core is developed. You need it as dependency in your projects
  - This is where you can participate in the development of Shopware through pull requests 
  - It's split into multiple repositories for production setups, all read-only
- [`shopware/development`](https://github.com/shopware/development) is the development template
  - **This is where your journey with shopware starts**
  - Installation see below!

## Quickstart / Installation

A full installation guide covering different dev environments is available in the [docs](https://docs.shopware.com/en/shopware-platform-dev-en/system-guide/installation).

*For the impatient reader, here is a tl;dr using docker.*

Let's start by cloning the development template:

```bash
> git clone git@github.com:shopware/development.git
```

You now have the application template for the Shopware Platform in the directory `development`, we now change into it:

```bash
> cd development
```

Only if you want to work with the Shopware platform code itself, e.g. in order to create a pull request for it, you should clone the platform code manually. Before doing so, empty the existing platform directory.

```bash
> rm platform/.gitkeep
> git clone git@github.com:shopware/platform
> git checkout @ platform/.gitkeep
```

Build and start the containers:

```bash
> ./psh.phar docker:start
```

Access the application container:

```bash
> ./psh.phar docker:ssh
```

Execute the installer:

```bash
> ./psh.phar install 
```

This may take a while since many caches need to be generated on first execution, but only on first execution.

To be sure that the installation succeeded, just open the following URL in your favorite browser: [localhost:8000](http://localhost:8000/)

[Now you're all set to start developing your first plugin.](https://docs.shopware.com/en/shopware-platform-dev-en/internals/plugins/plugin-quick-start?category=shopware-platform-dev-en/internals/plugins)

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

Whether plugin, theme or marketing tool: You can easily extend the functionality of your shop with over 3,500 available plugins in the Community Store.
 
[store.shopware.com](https://store.shopware.com)

### Academy

Do you want to become a Shopware expert or get a sneak peek into the software? Find a training session that is individually tailored to your interests.
 
[shopware.com/academy/](https://www.shopware.com/academy/)
 
### Shopware Community Day

Held annually, the Shopware Community Day informs ecommerce enthusiasts from across Europe about the current state - and future - of digital commerce.
 
[scd.shopware.com](https://scd.shopware.com)

## Contribution

First of all - Every contribution is meaningful, so thank you for participating.

You want to participate in the development of Shopware? There are many ways to contribute:

-   Submitting pull requests
-   Reporting issues on the [issue tracker](https://issues.shopware.com/)
-   Discuss shopware on e.g [Slack](https://slack.shopware.com) or our [forum](https://forum.shopware.com/categories/shopware-6)
- Write a translation for shopware on [crowdin](https://crowdin.com/project/shopware6) 

You have a question regarding contribution, or you want to contribute in another way?

Please write us an email: contributors@shopware.com

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
