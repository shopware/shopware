<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);
namespace Shopware\Core\Content\Flow\Dispatching\Message;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class FlowMessage {

    public function __construct(
        private readonly FlowEventAware $event,
        private readonly int $depth,
    ) {}

    public function getEvent(): FlowEventAware {
        return $this->event;
    }

    public function getDepth(): int {
        return $this->depth;
    }
}
