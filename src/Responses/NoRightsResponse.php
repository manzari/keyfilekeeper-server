<?php


namespace App\Responses;


use Symfony\Component\HttpFoundation\Response;

class NoRightsResponse extends ErrorResponse
{
    public function __construct(?string $right = null)
    {
        $message = 'You have no right to ' . $right ?? 'perform this action';
        parent::__construct($message, self::HTTP_UNAUTHORIZED, true);
    }
}