<?php


namespace App\Responses;

class NotFoundOrNoRightsResponse extends ErrorResponse
{
    public function __construct(string $objectName, bool $plural = false)
    {
        $message = 'The ' . $objectName . ' you are looking for '
        . ($plural ? 'do' : 'does') . ' not exist or you have no right to view '
        . ($plural ? 'them' : 'it') . '!';
        parent::__construct($message, self::HTTP_BAD_REQUEST);
    }
}