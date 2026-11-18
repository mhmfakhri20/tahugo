<?php

use App\Support\Geo;

test('haversine distance between known points', function () {
    expect(Geo::distanceMeters(-7.56, 110.82, -7.56, 110.82))->toBe(0.0);

    // 0.001° latitude ≈ 111,2 m.
    expect(Geo::distanceMeters(-7.56, 110.82, -7.561, 110.82))->toEqualWithDelta(111.2, 0.5);

    // Two placeholder outlet points ±15 km apart.
    expect(Geo::distanceMeters(-7.5600, 110.8200, -7.6000, 110.9500) / 1000)->toEqualWithDelta(15.0, 0.5);
});

test('maps url points to the coordinates', function () {
    expect(Geo::mapsUrl('-7.5600000', '110.8200000'))->toBe('https://www.google.com/maps?q=-7.5600000,110.8200000');
});
