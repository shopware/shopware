---
title: Omit sensitive email content from logs
issue: 22122
---

# API

* Changed password recovery and customer double-opt-in mail logs to contain delivery metadata instead of bodies, template data or complete messages.
  Sensitive-mail error logs contain exception classes and codes instead of messages and traces, retaining domain error codes for Shopware exceptions.
  Sensitive events no longer update stored mail-template sample data.
* Added `#[Shopware\Core\Framework\Event\SensitiveMail]` for mail events implementing `MailAware` to opt into metadata-only logging.
  Subclasses inherit the policy, and stored flows preserve the sensitivity flag.
  Direct callers can set `sensitive: true` in mail data or template data, or call `Mail::setSensitive(true)`.
  The classification is stored privately on the Shopware mail object and survives queue serialization.
  Messages that are not Shopware `Mail` objects, including signed messages, use metadata-only logging.
  Existing log entries and stored sample data are not modified.
  Previously queued flows retain their original classification.
  Previously queued Shopware mails without a stored flag keep the default non-sensitive classification.

___

# Administration

* Changed sensitive mail log details to show metadata without body previews.
  Other mail types retain content logging and body previews.
