<?php
declare(strict_types=1);
require_once(dirname(__FILE__) . '/Url.php');

class Media extends Url{

    protected $id;
    protected $mediaUrlHttps;
    protected $mediaAlt;

    public function __construct(array $json){
        parent::__construct($json);
        $this->id = (int)$json['id_str'];
        $this->mediaUrlHttps = $json['media_url_https'];
        $this->mediaAlt = $json['media_alt'];
    }

    public function getId(): int{
        return $this->id;
    }

    public function getMediaUrlHttps(): string{
        return $this->mediaUrlHttps;
    }

    public function getMediaAlt(): string{
        return $this->mediaAlt;
    }

}