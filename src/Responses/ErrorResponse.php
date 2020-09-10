<?php


namespace App\Responses;


class ErrorResponse extends JsonResponse
{
    public function __construct(?string $message = null, int $status = 200)
    {
        $content = [
          'status' => $status,
          'message' => $message ?? 'An error occurred'
        ];
        parent::__construct($content, $status, true);
    }
}