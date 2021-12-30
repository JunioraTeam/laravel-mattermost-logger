<?php

namespace ThibaudDauce\MattermostLogger;

use Illuminate\Support\Facades\URL;
use ThibaudDauce\Mattermost\Attachment;
use ThibaudDauce\Mattermost\Message as MattermostMessage;

class Message
{
    public $record;
    public $options;
    public $message;
    public $exception;

    public function __construct($record, $options)
    {
        $this->record = $record;
        $this->options = $options;
    }

    public static function fromArrayAndOptions($record, $options)
    {
        $messageBuilder = new self($record, $options);

        $messageBuilder->exception = $messageBuilder->popException();

        $messageBuilder->createBaseMessage();
        $messageBuilder->addTitleText();
        $messageBuilder->addExceptionAttachment();
        $messageBuilder->addContextAttachment();

        return $messageBuilder->message;
    }

    public function createBaseMessage()
    {
        $this->message = (new MattermostMessage)
            ->channel($this->options['channel'])
            ->username($this->options['username'])
            ->iconUrl($this->options['icon_url'] ? URL::to($this->options['icon_url']) : null);
    }

    public function addTitleText()
    {
        $this->message->text($this->title());
    }

    public function title()
    {
        $title = sprintf('**[%s%s]** %s', $this->record['level_name'], $this->exception ? ' ' . get_class($this->exception) : '', $this->record['message']);

        if ($this->shouldMention()) {
            $title .= sprintf(' (ping %s)', $this->mentions());
        }

        return $title;
    }

    public function mentions()
    {
        $mentions = array_map(function ($mention) {
            return str_start($mention, '@');
        }, $this->options['mentions']);

        return implode(', ', $mentions);
    }

    public function addExceptionAttachment()
    {
        if (! $this->exception) {
            return;
        }

        $this->attachment(function (Attachment $attachment) {
            $attachment->text(
                substr($this->exception->getTraceAsString(), 0, $this->options['max_attachment_length'])
            );
        });
    }

    public function popException()
    {
        if (! isset($this->record['context']['exception'])) {
            return;
        }

        $exception = $this->record['context']['exception'];
        unset($this->record['context']['exception']);

        return $exception;
    }

    public function addContextAttachment()
    {
        if (! isset($this->record['context']) or empty($this->record['context'])) {
            return;
        }

        $this->attachment(function (Attachment $attachment) {
            foreach ($this->record['context'] as $key => $value) {
                $stringifyValue = is_string($value) ? $value : json_encode($value);
                $attachment->field($key, $stringifyValue, strlen($stringifyValue) < $this->options['short_field_length']);
            }
        });
    }

    public function shouldMention()
    {
        return $this->record['level'] >= $this->options['level_mention'];
    }

    public function attachment($callback)
    {
        $this->message->attachment(function (Attachment $attachment) use ($callback) {
            if ($this->shouldMention()) {
                $attachment->error();
            }

            $callback($attachment);
        });
    }
}
