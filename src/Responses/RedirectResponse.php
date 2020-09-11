<?php


namespace App\Responses;


use Symfony\Component\HttpFoundation\Response;

class RedirectResponse extends Response
{
    public function __construct(string $path)
    {
        parent::__construct(null, 303, ['Location' => getenv('BASE_URL') . $path]);
    }
}