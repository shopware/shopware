<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

class TriggerDirection
{
    /**
     * FORWARD triggers are executed when an old application has to work with a newer Database
     * and has to keep it update-safe
     */
    const FORWARD = 'FORWARD';

    /**
     * BACKWARD triggers are executed when the new application works with the new Database
     * and has to keep it rollback-safe
     */
    const BACKWARD = 'BACKWARD';
}
