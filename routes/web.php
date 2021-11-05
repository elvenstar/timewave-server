<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->post('/api/astro', function (Illuminate\Http\Request $request) use ($router) {

  # Set path to ephemeris data files
    swe_set_ephe_path("./php-sweph");

    # Get timestamp
    $timestamp = gmdate("Y m d G i s");
    if ($request->input('timestamp')) {
        $timestamp = $request->input('timestamp');
    }

    # calc planet position
    list($y, $m, $d, $h, $mi, $s) = sscanf($timestamp, "%d %d %d %d %d %d");
    $jul_ut = swe_julday($y, $m, $d, ($h + $mi / 60 + $s / 3600), SE_GREG_CAL);

//    $planets['julday'] = $jul_ut;

    for ($i = SE_SUN; $i <= SE_PLUTO; $i++) {
        if ($i == SE_EARTH) {
            continue;
        }

        $xx = swe_calc_ut($jul_ut, $i, SEFLG_SPEED);
        if ($xx['rc'] < 0) { // error calling swe_calc_ut();
            $planets[$i] = array('error' => $xx['rc']);
            continue;
        }

        $planets[$i] = array(
          'name' => swe_get_planet_name($i),
          'lng' => $xx[0],
          'lat' => $xx[1],
          'speed' => $xx[3]
      );
    }

    // $out = ['planets' => json_encode($planets, JSON_PRETTY_PRINT)];

    # calc house cusps
//    define("GEO_LNG", 121.5);
//    define("GEO_LAT", 25.05);   // Taipei, Taiwan: 121E30, 25N03

    define('GEO_LNG', $request->input('lng'));
    define('GEO_LAT', $request->input('lat'));

    $yy = swe_houses($jul_ut, GEO_LAT, GEO_LNG, "P"); // P = Placidus.

    $houses = array();

    for ($i = 1; $i <= 12; $i ++) {
        $houses[$i] = array('lng' => $yy['cusps'][$i]);
    }

    // $out['houses'] = json_encode($houses, JSON_PRETTY_PRINT);

    $planets = collect($planets)
        ->keyBy('name')
//        ->reject(function($f) {
//            if (array_key_exists('error', $f)) return false;
//        })
        ->map(function ($p) {
            return array(
                round($p['lng'], 2),
                round($p['lat'], 2)
            );
        })->toArray();

    return [
        'planets' => $planets,
        // 'houses' => $houses,
        'cusps' => collect($houses)->map(function ($house) {
            return round($house['lng'], 2);
        })->flatten()->toArray()
    ];
});


$router->get('/api/astro', function (Illuminate\Http\Request $request) use ($router) {

    # Set path to ephemeris data files
    swe_set_ephe_path("./php-sweph");

    # calc planet position
    list($y, $m, $d, $h, $mi, $s) = sscanf(gmdate("Y m d G i s"), "%d %d %d %d %d %d");
    $jul_ut = swe_julday($y, $m, $d, ($h + $mi / 60 + $s / 3600), SE_GREG_CAL);

//    $planets['julday'] = $jul_ut;

    for ($i = SE_SUN; $i <= SE_PLUTO; $i++) {
        if ($i == SE_EARTH) {
            continue;
        }
        $xx = swe_calc_ut($jul_ut, $i, SEFLG_SPEED);
        if ($xx['rc'] < 0) { // error calling swe_calc_ut();
            $planets[$i] = array('error' => $xx['rc']);
            continue;
        }

        $planets[$i] = array(
            'name' => swe_get_planet_name($i),
            'lng' => $xx[0],
            'lat' => $xx[1],
            'speed' => $xx[3]
        );
    }

    // $out = ['planets' => json_encode($planets, JSON_PRETTY_PRINT)];

    # calc house cusps
    define("GEO_LNG", -25.98);
    define("GEO_LAT", 28.10);

    $yy = swe_houses($jul_ut, GEO_LAT, GEO_LNG, "P"); // P = Placidus.

    $houses = array();

    for ($i = 1; $i <= 12; $i ++) {
        $houses[$i] = array('lng' => $yy['cusps'][$i]);
    }

    // $out['houses'] = json_encode($houses, JSON_PRETTY_PRINT);

    return [
        'planets' => collect($planets)
            ->keyBy('name')
//            ->reject(function($f) {
//                if (array_key_exists('error', $f)) return false;
//            })
            ->map(function ($p) {
                return array(
                    round($p['lng'], 2),
                    round($p['lat'], 2)
                );
            })->toArray(),
        'cusps' => collect($houses)->map(function ($house) {
            return round($house['lng'], 2);
        })->flatten()->toArray()
    ];
});
