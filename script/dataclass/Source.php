<?php
declare(strict_types=1);

class Source{

    protected $name;
    protected $url;

    public function __construct(string $sourceHtml){
        preg_match('|<a href="(.*?)" rel="nofollow">(.*?)</a>|', $sourceHtml, $m);
        $this->name = $m[2];
        $this->url = $m[1];
    }

    public function getName(): string{
        return $this->name;
    }

    public function getUrl(): string{
        return $this->url;
    }

}