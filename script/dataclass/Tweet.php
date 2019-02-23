<?php
declare(strict_types=1);
require_once(dirname(__FILE__) . '/UserMention.php');
require_once(dirname(__FILE__) . '/User.php');
require_once(dirname(__FILE__) . '/Source.php');
require_once(dirname(__FILE__) . '/Url.php');
require_once(dirname(__FILE__) . '/Media.php');
require_once(dirname(__FILE__) . '/InReplyTo.php');

class Tweet{

    protected $id;
    protected $text;
    protected $createdAt;
    protected $source;
    protected $inReplyTo;
    protected $user;
    protected $userMentions;
    protected $medias;
    protected $urls;
    protected $geo;
    protected $retweetedTweet;

    public function __construct(array $json){
        $this->id = (int)$json['id_str'];
        $this->text = $json['text'];
        $this->createdAt = strtotime($json['created_at']);
        $this->source = new Source($json['source']);
        $this->inReplyTo = new InReplyTo($json);

        $this->user = new User($json['user']);
        $this->userMentions = [];
        foreach($json['entities']['user_mentions'] as $mention){
            $this->userMentions[] = new UserMention($mention);
        }

        $this->medias = [];
        foreach($json['entities']['media'] as $media){
            $this->medias[] = new Media($media);
        }

        $this->urls = [];
        foreach($json['entities']['urls'] as $url){
            $this->urls[] = new Url($url);
        }

        $this->geo = null;
        if(isset($json['geo']['coordinates'])){
            $this->geo = implode(',', $json['geo']['coordinates']);
        }

        $this->retweetedTweet = null;
        if(isset($json['retweeted_status'])){
            $this->retweetedTweet = new Tweet($json['retweeted_status']);
        }

    }

    public function getId(): int{
        return $this->id;
    }

    public function getText(): string{
        return $this->text;
    }

    public function getCreatedAt(): int{
        return $this->createdAt;
    }

    public function getSource(): Source{
        return $this->source;
    }

    public function getInReplyTo(): InReplyTo{
        return $this->inReplyTo;
    }

    public function getUser(): User{
        return $this->user;
    }

    public function getUserMentions(): array{
        return $this->userMentions;
    }

    public function getMedias(): array{
        return $this->medias;
    }

    public function getUrls(): array{
        return $this->urls;
    }

    public function getGeo(): ?string{
        return $this->geo;
    }

    public function getRetweetedTweet(): ?Tweet{
        return $this->retweetedTweet;
    }

}