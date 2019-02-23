<?php
declare(strict_types=1);

class InReplyTo{

    protected $inReplyToStatusId;
    protected $inReplyToUserId;
    protected $inReplyToScreenName;

    public function __construct(array $json){
        $this->inReplyToStatusId = isset($json['in_reply_to_status_id_str']) ?
            (int)$json['in_reply_to_status_id_str'] : null;
        $this->inReplyToUserId = isset($json['in_reply_to_user_id_str']) ?
            (int)$json['in_reply_to_user_id_str'] : null;
        $this->inReplyToScreenName = isset($json['in_reply_to_screen_name']) ?
            $json['in_reply_to_screen_name'] : null;
    }

    public function getInReplyToStatusId(): ?int{
        return $this->inReplyToStatusId;
    }

    public function getInReplyToUserId(): ?int{
        return $this->inReplyToUserId;
    }

    public function getInReplyToScreenName(): ?string{
        return $this->inReplyToScreenName;
    }

}