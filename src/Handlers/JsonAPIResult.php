<?php


namespace Micropoly\Handlers;


class JsonAPIResult
{
    public $data;
    public int $statuscode = 200;

    public function __construct($data, int $statuscode = 200)
    {
        $this->data = $data;
        $this->statuscode = $statuscode;
    }

    public function send(): void
    {
        http_response_code($this->statuscode);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($this->data);
    }
}