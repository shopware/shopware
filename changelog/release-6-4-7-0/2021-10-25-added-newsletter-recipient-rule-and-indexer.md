---
title: Added newsletter recipient rule and indexer
issue: NEXT-13720
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `newsletterSalesChannelIds` field to customer definition
* Added `NewsletterRecipientIndexingMessage`, `NewsletterRecipientIndexer` and `CustomerNewsletterSalesChannelsUpdater` to index sales channel IDs the customer is subscribed to
* Added `NewsletterRecipientDeletedSubscriber` to remove previously indexed newsletter sales channel IDs from customer when newsletter recipients are deleted
* Added `IsNewsletterRecipientRule` to match customers subscribed to newsletter of current sales channel
___
# Administration
* Added `sw-condition-is-newsletter-recipient` component
