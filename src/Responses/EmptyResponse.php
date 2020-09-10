<?php


namespace App\Responses;


use Symfony\Component\HttpFoundation\Response;

class EmptyResponse extends Response
{
    public function __construct()
    {
        parent::__construct(null,self::HTTP_NO_CONTENT);
    }
}