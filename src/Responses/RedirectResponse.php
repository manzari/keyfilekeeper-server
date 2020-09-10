<?php


namespace App\Responses;


use Symfony\Component\HttpFoundation\Response;

class RedirectResponse extends Response
{
    public function __construct(string $url)
    {
        parent::__construct(null, 303, ['Location' => $url]);
    }
}