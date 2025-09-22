<?php

namespace App\Services;

class CurrencyService
{
    public static function getCurrencies(): array
    {
        return [
            'USD' => ['symbol' => '$', 'name' => 'US Dollar', 'flag' => '🇺🇸'],
            'EUR' => ['symbol' => '€', 'name' => 'Euro', 'flag' => '🇪🇺'],
            'GBP' => ['symbol' => '£', 'name' => 'British Pound', 'flag' => '🇬🇧'],
            'JPY' => ['symbol' => '¥', 'name' => 'Japanese Yen', 'flag' => '🇯🇵'],
            'CAD' => ['symbol' => 'C$', 'name' => 'Canadian Dollar', 'flag' => '🇨🇦'],
            'AUD' => ['symbol' => 'A$', 'name' => 'Australian Dollar', 'flag' => '🇦🇺'],
            'CHF' => ['symbol' => 'CHF', 'name' => 'Swiss Franc', 'flag' => '🇨🇭'],
            'CNY' => ['symbol' => '¥', 'name' => 'Chinese Yuan', 'flag' => '🇨🇳'],
            'SEK' => ['symbol' => 'kr', 'name' => 'Swedish Krona', 'flag' => '🇸🇪'],
            'NZD' => ['symbol' => 'NZ$', 'name' => 'New Zealand Dollar', 'flag' => '🇳🇿'],
            'MXN' => ['symbol' => '$', 'name' => 'Mexican Peso', 'flag' => '🇲🇽'],
            'SGD' => ['symbol' => 'S$', 'name' => 'Singapore Dollar', 'flag' => '🇸🇬'],
            'HKD' => ['symbol' => 'HK$', 'name' => 'Hong Kong Dollar', 'flag' => '🇭🇰'],
            'NOK' => ['symbol' => 'kr', 'name' => 'Norwegian Krone', 'flag' => '🇳🇴'],
            'KRW' => ['symbol' => '₩', 'name' => 'South Korean Won', 'flag' => '🇰🇷'],
            'TRY' => ['symbol' => '₺', 'name' => 'Turkish Lira', 'flag' => '🇹🇷'],
            'RUB' => ['symbol' => '₽', 'name' => 'Russian Ruble', 'flag' => '🇷🇺'],
            'INR' => ['symbol' => '₹', 'name' => 'Indian Rupee', 'flag' => '🇮🇳'],
            'BRL' => ['symbol' => 'R$', 'name' => 'Brazilian Real', 'flag' => '🇧🇷'],
            'ZAR' => ['symbol' => 'R', 'name' => 'South African Rand', 'flag' => '🇿🇦'],
        ];
    }

    public static function getCurrencySymbol(string $currencyCode): string
    {
        $currencies = self::getCurrencies();
        return $currencies[$currencyCode]['symbol'] ?? '$';
    }

    public static function getCurrencyName(string $currencyCode): string
    {
        $currencies = self::getCurrencies();
        return $currencies[$currencyCode]['name'] ?? 'US Dollar';
    }

    public static function getCurrencyFlag(string $currencyCode): string
    {
        $currencies = self::getCurrencies();
        return $currencies[$currencyCode]['flag'] ?? '🇺🇸';
    }

    public static function formatAmount(float $amount, string $currencyCode): string
    {
        $symbol = self::getCurrencySymbol($currencyCode);
        
        // For currencies that typically show symbol after amount
        $symbolAfter = ['SEK', 'NOK'];
        
        if (in_array($currencyCode, $symbolAfter)) {
            return number_format($amount, 2) . ' ' . $symbol;
        }
        
        return $symbol . number_format($amount, 2);
    }
}