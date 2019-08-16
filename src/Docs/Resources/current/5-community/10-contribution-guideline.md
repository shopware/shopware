[titleEn]: <>(Contribution Guideline)
[metaDescriptionEn]: <>(Contribution guideline for Shopware Platform)

## Introduction
First of all, thank you!
You have decided to contribute code to our software and become a member of the large shopware community.
We appreciate your hard work and want to handle it with the most possible respect.

To ensure the quality of our code and our products we have created a small guideline we all should endorse to.
It helps you and us to collaborate with our software.
Following these guidelines will help us to integrate your changes in our daily workflow. 

## Requirements for a successful pull request
To avoid that your pull request gets rejected, you should always check that you provided all necessary information,
so that we can integrate your changes in our internal workflow very easily.
Here is a check-list with some requirements you should always consider when committing new changes.

- Did you fill out the [pull request info template](https://github.com/shopware/platform/blob/master/.github/PULL_REQUEST_TEMPLATE.md) as detailed as possible?
- Did you create an entry in the [platform updates](https://github.com/shopware/platform/tree/master/src/Docs/Resources/platform-updates) section with a small documentation of your changes?
- Does your pull request address the correct shopware version? Breaks and features cannot be merged in a patch release.
- Is your implementation missing some important parts? For example translations, downward compatibility, compatibility to important plugins, etc.
- Did you provide the necessary tests for your implementation?
- Is there already an existing pull request tackling the same issue?
- Write your commit messages in English, have them short and descriptive and squash your commits meaningfully.

Pull requests which do not fulfill these requirements will never be accepted by our team.
To avoid that your changes go through unnecessary workflow cycles make sure to check this list with every pull request.

## The developing workflow on GitHub
When you create a new pull request on GitHub normally it will get a first sight within a week.
We do regular meetings to screen all new pull requests on GitHub.
In this meeting there is a team of shopware developers of different specialisations which will discuss your changes.
Together we decide what will happen next to your pull request.

## Why a pull request gets declined
So the worst thing happened, your pull request was declined. No reason to be upset. We know that it sometimes can be hard to understand why your pull request was rejected. We want be as transparent as possible, but sometimes it can also rely on internal decisions. Here is a list with common reasons why we reject a pull request.

- The pull request does not fulfill the requirements of the list above.
- You did not updated your pull request with the necessary info after a specific label was added.
- The change you made is already a part of a current change by shopware and is handled internally.
- The benefit of your change is not relevant for the whole product but only for your personal intent.
- The benefit of your change is too minor. Sometimes we do not have enough resources to handle every small change.
- Your change implements a feature which does not fit to our roadmap or our company values.
