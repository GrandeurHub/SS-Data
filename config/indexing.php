<?php

$weeks = array_map(fn ($week) => ['value' => (string) $week, 'label' => "Week {$week}"], range(1, 52));

$months = array_map(
    fn ($month) => ['value' => $month, 'label' => $month],
    ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
);

$semesters = [
    ['value' => '1st', 'label' => '1st Semester'],
    ['value' => '2nd', 'label' => '2nd Semester'],
];

$years = array_map(fn ($year) => ['value' => (string) $year, 'label' => (string) $year], range(2026, 1970));

return [

    // Disk that holds uploads. "public" lives in storage/app/public and is exposed
    // through the public/storage symlink (php artisan storage:link).
    'disk' => env('INDEXING_DISK', 'public'),

    'categories' => [
        'prices' => [
            'label' => 'Prices',
            'has_region' => false,
            'items' => ['Palay', 'White Corn', 'Yellow Corn', 'Fertilizer', 'Pesticides', 'Farmgate Prices', 'Wholesale Prices', 'Retail Prices', 'World Prices'],
        ],
        'productions' => [
            'label' => 'Productions',
            'has_region' => true,
            'items' => ['Palay', 'White Corn', 'Yellow Corn', 'Fertilizer'],
        ],
        'imports' => [
            'label' => 'Imports',
            'has_region' => false,
            'items' => ['Rice', 'Pesticides', 'Fertilizer'],
        ],
        'exports' => [
            'label' => 'Exports',
            'has_region' => false,
            'items' => ['Rice', 'Pesticides', 'Fertilizer'],
        ],
    ],

    'periods' => [
        'weekly'   => ['label' => 'Weekly',   'values' => $weeks],
        'monthly'  => ['label' => 'Monthly',  'values' => $months],
        'semester' => ['label' => 'Semester', 'values' => $semesters],
        'yearly'   => ['label' => 'Yearly',   'values' => $years],
    ],

    'regions' => [
        'National Capital Region (NCR)',
        'Cordillera Administrative Region (CAR)',
        'Region I - Ilocos Region',
        'Region II - Cagayan Valley',
        'Region III - Central Luzon',
        'Region IV-A - CALABARZON',
        'MIMAROPA Region',
        'Region V - Bicol Region',
        'Region VI - Western Visayas',
        'Negros Island Region (NIR)',
        'Region VII - Central Visayas',
        'Region VIII - Eastern Visayas',
        'Region IX - Zamboanga Peninsula',
        'Region X - Northern Mindanao',
        'Region XI - Davao Region',
        'Region XII - SOCCSKSARGEN',
        'Region XIII - Caraga',
        'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)',
    ],

    // Allowed upload types and the size limit in kilobytes.
    'extensions' => ['pdf', 'csv', 'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'odt', 'ods', 'rtf', 'json', 'zip'],
    'max_kb' => 20480,

    // Most files packed into one "Download all" ZIP.
    'bulk_limit' => 200,
];
