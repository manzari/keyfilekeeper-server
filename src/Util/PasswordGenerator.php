<?php


namespace App\Util;

/**
 * Class PasswordGenerator
 * @package App\Util
 */
class PasswordGenerator
{
    private $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @param int $length
     * @return string
     */
    public function generate(int $length = 21): string
    {
        $charactersLength = strlen($this->characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $this->characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}