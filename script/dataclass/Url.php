<?php
declare(strict_types=1);

class Url{

    protected $url;
    protected $expanded_url;
    protected $display_url;

    public function __construct(array $json){
        $this->url = $json['url'];
        $this->expanded_url = $json['expanded_url'];
        $this->display_url = $json['display_url'];
    }

    public function getUrl(): string{
        return $this->url;
    }

    public function getExpandedUrl(): string{
        return $this->expanded_url;
    }

    public function getDisplayUrl(): string{
        return $this->display_url;
    }

}