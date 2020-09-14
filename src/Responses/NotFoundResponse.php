<?php


namespace App\Responses;


class NotFoundResponse extends ErrorResponse
{
    public function __construct(?string $objectName = null)
    {
        $message = 'The ' . ($objectName ?? 'object') . ' you are looking for does not exist!';
        parent::__construct($message, self::HTTP_NOT_FOUND, true);
    }
}