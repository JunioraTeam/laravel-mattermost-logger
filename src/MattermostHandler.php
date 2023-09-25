<?php

namespace ThibaudDauce\MattermostLogger;

use ThibaudDauce\Mattermost\Mattermost;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class MattermostHandler extends AbstractProcessingHandler
{
    private Mattermost $mattermost;
    private array $options = [];

    public function __construct(Mattermost $mattermost, $options = [])
    {
        $this->options = array_merge([
            'webhook' => null,
            'channel' => 'town-square',
            'icon_url' => null,
            'username' => 'Laravel Logs',
            'level' => Level::Info,
            'level_mention' => Level::Error,
            'mentions' => ['@here'],
            'short_field_length' => 62,
            'max_attachment_length' => 6000,
        ], $options);

        $this->mattermost = $mattermost;
    }

    public function write(LogRecord|array $record): void
    {
        if ($record['level'] < $this->options['level']) {
            return;
        }

        $message = Message::fromArrayAndOptions($record, $this->options);

        $this->mattermost->send($message, $this->options['webhook']);
    }
}
