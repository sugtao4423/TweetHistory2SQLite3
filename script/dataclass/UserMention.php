<?php
declare(strict_types=1);

class UserMention{

    protected $id;
    protected $name;
    protected $screenName;

    public function __construct(array $json){
        $this->id = (int)$json['id_str'];
        $this->name = $json['name'];
        $this->screenName = $json['screen_name'];
    }

    public function getId(): int{
        return $this->id;
    }

    public function getName(): string{
        return $this->name;
    }

    public function getScreenName(): string{
        return $this->screenName;
    }

}