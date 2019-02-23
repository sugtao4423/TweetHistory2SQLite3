<?php
declare(strict_types=1);
require_once(dirname(__FILE__) . '/UserMention.php');

class User extends UserMention{

    protected $protected;
    protected $profileImageUrlHttps;
    protected $verified;

    public function __construct(array $json){
        parent::__construct($json);
        $this->protected = $json['protected'];
        $this->profileImageUrlHttps = $json['profile_image_url_https'];
        $this->verified = $json['verified'];
    }

    public function getProtected(): bool{
        return $this->protected;
    }

    public function getProfileImageUrlHttps(): string{
        return $this->profileImageUrlHttps;
    }

    public function getVerified(): bool{
        return $this->verified;
    }

}