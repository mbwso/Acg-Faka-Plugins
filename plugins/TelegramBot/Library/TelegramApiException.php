<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library;

class TelegramApiException extends \RuntimeException
{
    public array $apiResponse;

    public function __construct(string $message, int $code = 0, array $apiResponse = [])
    {
        parent::__construct($message, $code);
        $this->apiResponse = $apiResponse;
    }
}
