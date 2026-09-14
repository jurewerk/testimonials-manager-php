<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Shared behaviour for the mock providers.
 *
 * These deliberately call nothing and cost nothing. They return the text
 * prefixed with the country code in square brackets, exactly as the assignment
 * specifies, which is enough to prove the interface, the provider choice and
 * the persistence all work.
 */
abstract class MockProvider implements AiProvider
{
    /** Plausible names per country, split by gender: [male, female, unisex]. */
    private const NAMES = [
        'SI' => ['Janez Novak', 'Ana Novak', 'Saša Novak'],
        'IT' => ['Mario Rossi', 'Giulia Rossi', 'Andrea Rossi'],
        'DE' => ['Lukas Müller', 'Anna Müller', 'Alex Müller'],
        'AT' => ['Lukas Gruber', 'Lena Gruber', 'Alex Gruber'],
        'FR' => ['Louis Martin', 'Emma Martin', 'Camille Martin'],
        'ES' => ['Javier García', 'Lucía García', 'Alex García'],
        'PT' => ['João Silva', 'Maria Silva', 'Alex Silva'],
        'NL' => ['Daan de Vries', 'Emma de Vries', 'Sam de Vries'],
        'PL' => ['Jakub Nowak', 'Zofia Nowak', 'Alex Nowak'],
        'CZ' => ['Jan Novák', 'Eliška Nováková', 'Alex Novák'],
        'SK' => ['Peter Horváth', 'Zuzana Horváthová', 'Alex Horváth'],
        'HU' => ['László Nagy', 'Eszter Nagy', 'Alex Nagy'],
        'HR' => ['Ivan Horvat', 'Ana Horvat', 'Alex Horvat'],
        'RS' => ['Nikola Jovanović', 'Milica Jovanović', 'Alex Jovanović'],
        'BG' => ['Georgi Ivanov', 'Maria Ivanova', 'Alex Ivanov'],
        'RO' => ['Andrei Popescu', 'Elena Popescu', 'Alex Popescu'],
        'GR' => ['Giorgos Papadopoulos', 'Maria Papadopoulou', 'Alex Papadopoulos'],
        'LT' => ['Tomas Kazlauskas', 'Greta Kazlauskienė', 'Alex Kazlauskas'],
        'LV' => ['Jānis Bērziņš', 'Anna Bērziņa', 'Alex Bērziņš'],
        'EE' => ['Jaan Tamm', 'Liis Tamm', 'Alex Tamm'],
        'EN' => ['James Smith', 'Emma Smith', 'Alex Smith'],
    ];

    public function translate(string $text, string $countryCode): string
    {
        return '['.strtoupper($countryCode).'] '.$text;
    }

    public function generateAuthorName(string $countryCode, string $gender): string
    {
        $country = strtoupper($countryCode);
        $names = self::NAMES[$country] ?? self::NAMES['EN'];

        $index = match ($gender) {
            'male' => 0,
            'female' => 1,
            default => 2,
        };

        return '['.$country.'] '.$names[$index];
    }
}
