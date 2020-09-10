<?php


namespace App\Responses;

class NotFoundOrNoRightsResponse extends ErrorResponse
{
    public function __construct(string $objectName, bool $plural = false)
    {
        $doDoes = $plural ? 'do' : 'does';
        $themIt = $plural ? 'them' : 'it';
        $message = 'The ' . $objectName . ' you are looking for '
            . $doDoes . ' not exist or you have no right to view ' . $themIt . '!';
        parent::__construct($message, self::HTTP_BAD_REQUEST, true);
    }
}