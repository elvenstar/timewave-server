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

function get_planet_positions($jul_ut)
{
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

    return $planets;
}

function basic_chart($jul_ut)
{
    $planets = get_planet_positions($jul_ut);


    // $out = ['planets' => json_encode($planets, JSON_PRETTY_PRINT)];

    # calc house cusps
//    define("GEO_LNG", 121.5);
//    define("GEO_LAT", 25.05);   // Taipei, Taiwan: 121E30, 25N03

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
}

function natal_chart($timestamp, $lat, $lng)
{
    # calc planet position
    list($y, $m, $d, $h, $mi, $s) = sscanf($timestamp, "%d %d %d %d %d %d");
    $jul_ut = swe_julday($y, $m, $d, ($h + $mi / 60 + $s / 3600), SE_GREG_CAL);

    define('GEO_LNG', $lng);
    define('GEO_LAT', $lat);

//    $planets['julday'] = $jul_ut;
    return basic_chart($jul_ut);
}

function format_planets($planets)
{
    return collect($planets)
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
}

function solar_return($timestamp, $lat, $lng, $year)
{
    // TODO: time at midnight of birthday
    # calc planet position
    list($y, $m, $d, $h, $mi, $s) = sscanf($timestamp, "%d %d %d %d %d %d");
    $birthday_jul_ut = swe_julday($y, $m, $d, ($h + $mi / 60 + $s / 3600), SE_GREG_CAL);

    // TODO: time at midnight of this year
    $solar_return_jul_ut = swe_julday($year, $m, $d, ($h + $mi / 60 + $s / 3600), SE_GREG_CAL);

    // TODO: calculate difference
    $birthday_planets = get_planet_positions($birthday_jul_ut);
    $solar_return_planets = get_planet_positions($solar_return_jul_ut);

    define('GEO_LNG', $lng);
    define('GEO_LAT', $lat);

    $birthday_planets = format_planets($birthday_planets);
    $solar_return_planets = format_planets($solar_return_planets);

    // TODO: while true, adjust time until we get the birthday sun degree
    $initial_time = ($h + $mi / 60 + $s / 3600);

    // 217.78 > 217.98
    while ($solar_return_planets["Sun"][0] > $birthday_planets["Sun"][0]) {
        // increment solar return time
        $initial_time -= 0.1;
        $solar_return_jul_ut = swe_julday($year, $m, $d, $initial_time, SE_GREG_CAL);
        $solar_return_planets = get_planet_positions($solar_return_jul_ut);
        $solar_return_planets = format_planets($solar_return_planets);
    }

    // compute solar return chart
    return basic_chart($solar_return_jul_ut, $lat, $lng);
}


$router->get('/', function () use ($router) {
    return 'Timewave Server: PHP Swiss Emphemeris.';
});

$router->post('/api/astro', function (Illuminate\Http\Request $request) use ($router) {

    # Set path to ephemeris data files
    swe_set_ephe_path("./php-sweph");

    # Get timestamp
    $timestamp = gmdate("Y m d G i s");
    if ($request->input('timestamp')) {
        $timestamp = $request->input('timestamp');
    }

    # Get chart type
    $type = $request->input('type');

    # Get coordinates
    $lat = $request->input('lat');
    $lng = $request->input('lng');

    switch ($type) {
      case 'SOLAR_RETURN': {

        # Get year
        $year = gmdate("Y");
        if ($request->input('year')) {
            $year = $request->input('year');
        }

        return solar_return($timestamp, $lat, $lng, $year);
      }
      default: {
        return natal_chart($timestamp, $lat, $lng);
      }
    }
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
