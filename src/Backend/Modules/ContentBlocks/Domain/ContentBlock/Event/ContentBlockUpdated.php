<?php

namespace App\Backend\Modules\ContentBlocks\Domain\ContentBlock\Event;

final class ContentBlockUpdated extends ContentBlockEvent
{
    /**
     * @var string The name the listener needs to listen to to catch this event.
     */
    const EVENT_NAME = 'content_blocks.event.content_block_updated';
}
