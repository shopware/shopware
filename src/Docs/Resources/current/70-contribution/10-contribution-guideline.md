[titleEn]: <>(Contribution Guideline)
[metaDescriptionEn]: <>(Contribution guideline for Shopware Platform)
[hash]: <>(article:contribution_guidelines)

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
- Did you create a changelog file with documentation of your changes? You can find detailed information about writing changelog [here](https://github.com/shopware/platform/blob/master/adr/2020-08-03-Implement-New-Changelog.md)!
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
We will set one of the following labels which indicate the status of the pull request. Here is a list of all possible states.

<style>
    .table-style-placeholder + table td:nth-child(1) {
        width: 150px;
        text-align: center;
    }
    
    .table-style-placeholder + table td:nth-child(1) img {
        border: 0 none;
        display: inline-block;
    }
</style>
<div class="table-style-placeholder"></div>
| GitHub Label / Tag                                                  |   What does it mean?                                                                                                                                                                                                                                                                                                                                     |
|:-------------------------------------------------------------------:|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------| 
| ![GitHub label incomplete](./img/github-label-incomplete.png)       | Your pull request is incomplete. It is either missing some of the necessary information, or your code implementation is not sufficient to fix the issue. Mostly there will be a comment by our developers which gives you further information of what is missing. <br><br>**Important:** The label "Incomplete" means that you have to take action! After your pull request has this label assigned, you have up to two weeks to update the pull request and provide the missing information or implementation. If there is no reaction from you within those two weeks, your pull request can be declined due to inactivity. This procedure ensures there are no orphaned pull requests in the backlog. |
| ![GitHub label declined](./img/github-label-declined.png)           | Your pull request was declined by our developers and is closed. No reason to be sad. It can have very different reasons. We understand that it sometimes can be hard to understand the reason behind this. Mostly there will be a comment by our developers about why it was declined.                                                                   |
| ![GitHub label scheduled](./img/github-label-scheduled.png)         | Yeaha! You made the first step towards the holy grail. Your changes had been reviewed by our developers and they decided that you provided a good benefit for our product. Your pull request will be imported to our ticket system and will go through our internal workflow. You will find a comment containing the ticket number to follow the status. |
| ![GitHub label quickpick](./img/github-label-quickpick.png)         | You are a lucky one! The changes you provide seems to be easy to test and implement. Our developers decided to quickly integrate this to our software. Probably there will be no ticket for this change to follow but you will be informed by the accepted label on this pull request, that your change was finally merged into the product.             |
| ![GitHub label accepted](./img/github-label-accepted.png)           | Your changes are finally accepted. The pull request passed our internal workflow. Your changes will be released with one of the next releases.                                                                                                                                                                                                           |
| ![GitHub label feature request](./img/github-label-feature.png)     | Your pull request includes a new feature which needs an internal discussion by our developers. We have to decide if the new feature provides a good benefit for the product and at which point on the roadmap it should be scheduled.                                                                                                                    |
| ![GitHub label refactoring](./img/github-label-refactoring.png)     | Your pull request includes a larger refactoring which needs an internal discussion by our developers. This label will mainly be set, when larger chunks of code have been re-written, renamed or moved to different directories.                                                                                                                         |
| ![GitHub label missing tests](./img/github-label-missing-tests.png) | Your pull request is lacking necessary tests for your changes. E.g. Jest or Cypress tests for frontend changes or PHPUnit tests for backend changes.                                                                                                                                                                                                     |

## Why a pull request gets declined
So the worst thing happened, your pull request was declined. No reason to be upset. We know that it sometimes can be hard to understand why your pull request was rejected. We want be as transparent as possible, but sometimes it can also rely on internal decisions. Here is a list with common reasons why we reject a pull request.

- The pull request does not fulfill the requirements of the list above.
- You did not updated your pull request with the necessary info after a specific label was added.
- The change you made is already a part of a current change by shopware and is handled internally.
- The benefit of your change is not relevant for the whole product but only for your personal intent.
- The benefit of your change is too minor. Sometimes we do not have enough resources to handle every small change.
- Your change implements a feature which does not fit to our roadmap or our company values.
