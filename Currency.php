<?php

declare(strict_types=1);

use GuzzleHttp\Exception\GuzzleException;

class Currency
{
    const string CB_RATE_API_URL = 'https://cbu.uz/uz/arkhiv-kursov-valyut/json/';
    private GuzzleHttp\Client $http;
    private PDO               $pdo;

    public function __construct()
    {
        $this->http = new GuzzleHttp\Client(['base_uri' => self::CB_RATE_API_URL]);
        $this->pdo  = DB::connect();
    }

    /**
     * @throws GuzzleException
     */
    public function getRates()
    {
        return json_decode($this->http->get('')->getBody()->getContents());
    }

    /**
     * @return mixed
     * @throws GuzzleException
     */
    public function getUsd()
    {
        return $this->getRates()[0];
    }

    public function convert(
        int    $chatId,
        string $originalCurrency,
        string $targetCurrency,
        float  $amount
    ): string {
        $now    = date('Y-m-d H:i:s');
        $status = "{$originalCurrency}2{$targetCurrency}";
        $rate   = $this->getUsd()->Rate;

        $stmt = $this->pdo->prepare("INSERT INTO users (chat_id, amount,status, created_at) VALUES (:chatId, :amount, :status, :createdAt)");
        $stmt->bindParam(':chatId', $chatId);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':createdAt', $now);
        $stmt->execute();

        if ($originalCurrency === 'usd') {
            $result = $amount * $rate;
        } else {
            $result = $amount / $rate;
        }

        return number_format($result, 0, '', '\.'); // Escaping . (dot) is necessary for telegram markdown style
    }

    public function storeState(int $chatId, string $state): void
    {
        $query = "INSERT INTO states (chat_id, conversion_type, created_at) VALUES (:chatId, :conversionType, :createdAt)";
        $now   = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':chatId', $chatId);
        $stmt->bindParam(':conversionType', $state);
        $stmt->bindParam(':createdAt', $now);
        $stmt->execute();
    }

    public function calculateForBot(int $chatId, int|float $amount): string
    {
        $query = "SELECT conversion_type FROM states WHERE states.chat_id = :chatId ORDER BY created_at DESC LIMIT 1";
        $stmt  = $this->pdo->prepare($query);
        $stmt->bindParam(':chatId', $chatId);
        $stmt->execute();

        $conversionType = $stmt->fetchObject()->conversion_type;

        [$originalCurrency, $targetCurrency] = explode('2', $conversionType);

        return $this->convert((int) $chatId, $originalCurrency, $targetCurrency, $amount);
    }
}