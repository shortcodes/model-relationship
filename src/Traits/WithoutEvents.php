<?php

namespace Shortcodes\ModelRelationship\Traits;


trait WithoutEvents
{
    public function withoutTriggerEvents(callable $process)
    {
        $temp = $this->getEventDispatcher();

        $this->unsetEventDispatcher();

        $result = $process($this, $temp);

        $this->setEventDispatcher($temp);

        return $result;
    }
}