<?php

declare(strict_types=1);

namespace FixIt\Support;

/** West Malaysia service areas — primary market: Johor Bahru + Skudai (UTM). */
final class MalaysiaRegions
{
    public const PRIMARY = ['johor_bahru', 'skudai_utm'];

    /** @var array<string, array{label:string,center:array{0:float,1:float},minLat:float,maxLat:float,minLng:float,maxLng:float}> */
    private const REGIONS = [
        'johor_bahru' => [
            'label' => 'Johor Bahru',
            'center' => [1.4927, 103.7414],
            'minLat' => 1.40, 'maxLat' => 1.58,
            'minLng' => 103.65, 'maxLng' => 103.82,
        ],
        'skudai_utm' => [
            'label' => 'Skudai · UTM',
            'center' => [1.5595, 103.6383],
            'minLat' => 1.52, 'maxLat' => 1.58,
            'minLng' => 103.60, 'maxLng' => 103.67,
        ],
        'kul' => [
            'label' => 'Kuala Lumpur',
            'center' => [3.1390, 101.6869],
            'minLat' => 2.95, 'maxLat' => 3.35,
            'minLng' => 101.55, 'maxLng' => 101.85,
        ],
        'penang' => [
            'label' => 'Penang',
            'center' => [5.4141, 100.3288],
            'minLat' => 5.30, 'maxLat' => 5.50,
            'minLng' => 100.20, 'maxLng' => 100.45,
        ],
        'ipoh' => [
            'label' => 'Ipoh',
            'center' => [4.5975, 101.0901],
            'minLat' => 4.50, 'maxLat' => 4.70,
            'minLng' => 101.00, 'maxLng' => 101.20,
        ],
        'melaka' => [
            'label' => 'Melaka',
            'center' => [2.1896, 102.2501],
            'minLat' => 2.10, 'maxLat' => 2.35,
            'minLng' => 102.15, 'maxLng' => 102.35,
        ],
    ];

    /** @return array<string, array{label:string,center:array{0:float,1:float}}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::REGIONS as $id => $r) {
            $out[$id] = ['label' => $r['label'], 'center' => $r['center']];
        }
        return $out;
    }

    public static function label(string $region): string
    {
        return self::REGIONS[$region]['label'] ?? 'West Malaysia';
    }

    public static function isValid(string $region): bool
    {
        return isset(self::REGIONS[$region]);
    }

    /** Nearest region by centre distance; Skudai checked before JB when overlapping. */
    public static function detect(float $lat, float $lng): string
    {
        if ($lat >= self::REGIONS['skudai_utm']['minLat']
            && $lat <= self::REGIONS['skudai_utm']['maxLat']
            && $lng >= self::REGIONS['skudai_utm']['minLng']
            && $lng <= self::REGIONS['skudai_utm']['maxLng']) {
            return 'skudai_utm';
        }
        if ($lat >= self::REGIONS['johor_bahru']['minLat']
            && $lat <= self::REGIONS['johor_bahru']['maxLat']
            && $lng >= self::REGIONS['johor_bahru']['minLng']
            && $lng <= self::REGIONS['johor_bahru']['maxLng']) {
            return 'johor_bahru';
        }
        foreach (['kul', 'penang', 'ipoh', 'melaka'] as $id) {
            $r = self::REGIONS[$id];
            if ($lat >= $r['minLat'] && $lat <= $r['maxLat']
                && $lng >= $r['minLng'] && $lng <= $r['maxLng']) {
                return $id;
            }
        }
        return 'johor_bahru';
    }

    /** @return array{minLat:float,maxLat:float,minLng:float,maxLng:float}|null */
    public static function bounds(string $region): ?array
    {
        if (!isset(self::REGIONS[$region])) {
            return null;
        }
        $r = self::REGIONS[$region];
        return [
            'minLat' => $r['minLat'],
            'maxLat' => $r['maxLat'],
            'minLng' => $r['minLng'],
            'maxLng' => $r['maxLng'],
        ];
    }

    public static function regionForProvider(float $lat, float $lng): string
    {
        return self::detect($lat, $lng);
    }
}