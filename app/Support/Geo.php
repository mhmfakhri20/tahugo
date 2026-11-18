<?php

namespace App\Support;

/**
 * Geographic helpers. Distance is computed on the SERVER (ATT-2), never trusted from the client.
 */
final class Geo
{
    private const EARTH_RADIUS_M = 6_371_000;

    /**
     * Great-circle distance in meters between two WGS84 points (Haversine formula).
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Google Maps link for a point (ATT-7 "Lihat di peta").
     */
    public static function mapsUrl(float|string $lat, float|string $lng): string
    {
        return 'https://www.google.com/maps?q='.$lat.','.$lng;
    }
}
