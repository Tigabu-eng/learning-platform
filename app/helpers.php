<?php

if (! function_exists('money')) {
    function money($amount, ?string $currency = null): string
    {
        $currency ??= config('school.currency');
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'AUD' => 'A$'];
        $n = number_format((float) $amount, 2);
        return isset($symbols[$currency]) ? $symbols[$currency].$n : $currency.' '.$n;
    }
}
