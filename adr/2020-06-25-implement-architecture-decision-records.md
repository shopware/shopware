---
title: Implement architecture decision records
date: 2020-06-25
area: core
tags: [adr, workflow]
---

## Context
We should document architecture and technical decisions for the shopware platform. The documentation should be easy to understand and easy to follow. The workflow for new decisions should add to our existing workflows and should not block the whole development process. One solution could be the form of architecture decision records (ADR) as described in the following articles:

*  [Documenting Architecture Decisions](http://thinkrelevance.com/blog/2011/11/15/documenting-architecture-decisions)
*  [A Simple but Powerful Tool to Record Your Architectural Decisions](https://medium.com/better-programming/here-is-a-simple-yet-powerful-tool-to-record-your-architectural-decisions-5fb31367a7da)

## Decision
We will record architecture decisions (ADR) in markdown files directly in the platform repository. The workflow for ADRs will be integrated in the existing merge request workflow. This has the following advantages:

*  Decision records are an integral part of the development process
*  Decisions remain in sync with the code itself
*  The Git history is also the decision history
*  Decisions are public available and accessible for every developer
*  Also external developers can add new ADRs via GitHub pull requests
*  Decision finding can be asynchronous via comments in the corresponding merge request

## Consequences
From now on, every architecture decision, affecting the shopware platform or one of its components, has to be recorded in an ADR, following the described workflow.

In the following you find answers to the most important questions about ADRs and the new workflow:

**Who can/must create ADRs?**   
Every developer working with shopware platform!

**When do you have to create an ADR?**  
Have you made a significant decision that impacts how developers should write code in the shopware platform? Write an ADR! Here are some cases, which can help you to understand when to write an ADR:

*  Introducing new standards
*  Large code changes which have a huge impact on the software
*  Smaller changes, but which are used very often and would lead to duplicated efforts 

If you want a more detailed explanation, we recommend the article "[When should I write an Architecture Decision Record](https://engineering.atspotify.com/2020/04/14/when-should-i-write-an-architecture-decision-record/)".

**How can you create new ADRs?**  
The ADRs are markdown files inside the platform repository, located in the "adr" directory in the root of the repository. So new ADRs can simply be created via merge requests. The merge request is also the approval process for the ADR. Along with the ADR, all necessary code changes have to be added to the merge request, which are needed to implement the new decision. Add the "ADR" label to your merge request, so everyone can identify merge requests containing an ADR.  

**How many people have to approve an ADR?**  
* Two additional developers have to review the ADR
   *  One developer must be a member of the core development team
   *  One developer must be a member of a team, other than the team of the creator
* One product owner or higher role has to approve an ADR
** This part of the decision is superseded by [2021-11-05 - Adjust ADR approval rules for the new org structure](2021-11-05-adjust-adr-approval-rules.md), but the rest of this ADR is untouched.**

**Should counter decisions also be documented?**   
Not specific, but if there is more than one possible solution, all options should be outlined.

**How does an ADR look like?**  
You can use this first ADR as an orientation. The filename of the ADR should contain the date and a meaningful title. The content of the ADR should always use the following template:

```
# [Date] - [Title]
## Context
## Decision
## Consequences
```

**Which status can an ADR have?**  
The status of an ADR is symbolized by the directory. All ADR located in the main `/adr` directory are "accepted" and represent the current decision state of the software. The approval process is done via the merge request. When a new decision outdoes an older decision, the old decision has to be moved to the `/adr/_superseded` directory and a link to the new ADR has to be added.

**Can an ADR be changed?**  
When an ADR is accepted and merged in to the code, it can no longer be changed. If a decision is outdated or has to be changed, the ADR has to be superseded by a new ADR. Superseded ADRs have to be moved to the `/adr/_superseded` directory.
