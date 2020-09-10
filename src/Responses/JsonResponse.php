<?php


namespace App\Responses;


use Symfony\Component\HttpFoundation\Response;

class JsonResponse extends Response
{
    public function __construct($content = [], int $status = 200, bool $encode = false)
    {
        parent::__construct($encode ? json_encode($content) : $content, $status, ['application/json']);
    }
}