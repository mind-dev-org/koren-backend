<?php

namespace App\Controllers;

use App\Models\DeliverySlot;
use Vine\Core\Request;
use Vine\Core\Response;

class DeliveryController
{
    public function slots(Request $request): Response
    {
        $date = $request->query('date', date('Y-m-d', strtotime('+1 day')));
        $city = $request->query('city', 'Kyiv');

        $slots = DeliverySlot::query()
            ->where('date', '=', $date)
            ->where('city', '=', $city)
            ->orderBy('time_range', 'ASC')
            ->get();

        $slots = array_map(function($slot) {
            $slot['available']     = $slot['capacity_used'] < $slot['capacity_total'];
            $slot['capacity_left'] = $slot['capacity_total'] - $slot['capacity_used'];
            return $slot;
        }, $slots);

        return Response::success(['date' => $date, 'city' => $city, 'slots' => $slots]);
    }

    public function zones(Request $request): Response
    {
        return Response::success([
            'type'     => 'FeatureCollection',
            'features' => [
                [
                    'type'       => 'Feature',
                    'properties' => [
                        'zone_id'       => 1,
                        'name'          => 'Kyiv Central',
                        'color'         => '#C0533A',
                        'delivery_days' => ['Mon', 'Wed', 'Fri'],
                        'cutoff_time'   => '16:00',
                    ],
                    'geometry'   => [
                        'type'        => 'Polygon',
                        'coordinates' => [[[30.51, 50.44], [30.57, 50.44], [30.57, 50.48], [30.51, 50.48], [30.51, 50.44]]],
                    ],
                ],
            ],
        ]);
    }
}
