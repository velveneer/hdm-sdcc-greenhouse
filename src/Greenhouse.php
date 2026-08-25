<?php

declare(strict_types=1);

namespace Sdcc\Greenhouse;

final class Greenhouse
{
    private const TAU_TEMP_CLOSED = 900.0;

    private const TAU_TEMP_OPEN = 240.0;

    private const TAU_MOISTURE_WATERING = 90.0;
    private const TAU_MOISTURE_DRYING = 5400.0;

    private const MAX_STEP = 3600.0;

    public const ACTUATORS = [
        'pump' => ['on', 'off'],
        'fan' => ['on', 'off'],
        'lamp' => ['on', 'off'],
        'window' => ['open', 'closed'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function initial(float $now): array
    {
        return [
            'updatedAt' => $now,
            'temperature' => self::outsideTemperature($now) + 4.0,
            'moisture' => 45.0,
            'actuators' => [
                'pump' => 'off',
                'fan' => 'off',
                'lamp' => 'off',
                'window' => 'closed',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function advance(array $state, float $now): array
    {
        $dt = max(0.0, min($now - (float) $state['updatedAt'], self::MAX_STEP));
        $actuators = $state['actuators'];

        $outside = self::outsideTemperature($now);

        $target = $actuators['window'] === 'open' ? $outside + 1.0 : $outside + 6.0;

        if ($actuators['fan'] === 'on') {
            $target -= 3.0;
        }

        if ($actuators['lamp'] === 'on') {
            $target += 1.5;
        }

        $tau = $actuators['window'] === 'open'
            ? self::TAU_TEMP_OPEN
            : self::TAU_TEMP_CLOSED;

        $jitter = 0.15 * sin($now / 37.0);

        $state['temperature'] = self::approach(
            (float) $state['temperature'],
            $target,
            $dt,
            $tau,
        ) + $jitter;

        $watering = $actuators['pump'] === 'on';

        $state['moisture'] = self::clamp(
            self::approach(
                (float) $state['moisture'],
                $watering ? 95.0 : 12.0,
                $dt,
                $watering ? self::TAU_MOISTURE_WATERING : self::TAU_MOISTURE_DRYING,
            ),
            0.0,
            100.0,
        );

        $state['updatedAt'] = $now;

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, float|int>
     */
    public static function readings(array $state, float $now): array
    {
        return [
            'temperature' => round((float) $state['temperature'], 1),
            'moisture' => round((float) $state['moisture'], 1),
            'light' => self::light($state, $now),
        ];
    }

    /** @param array<string, mixed> $state */
    private static function light(array $state, float $now): int
    {
        $lux = (float) self::sunlight($now);

        if ($state['actuators']['lamp'] === 'on') {
            $lux += 400.0;
        }

        if ($state['actuators']['window'] === 'closed') {
            $lux *= 0.85;
        }

        return (int) round($lux);
    }

    private static function sunlight(float $now): int
    {
        $hour = self::hourOfDay($now);

        if ($hour < 6.0 || $hour > 18.0) {
            return 0;
        }

        return (int) round(900 * sin(M_PI * ($hour - 6.0) / 12.0));
    }

    private static function outsideTemperature(float $now): float
    {
        return 11.0 + 7.0 * sin(2 * M_PI * (self::hourOfDay($now) - 9.0) / 24.0);
    }

    private static function hourOfDay(float $now): float
    {
        return fmod($now, 86400.0) / 3600.0;
    }

    private static function approach(float $value, float $target, float $dt, float $tau): float
    {
        return $value + ($target - $value) * (1 - exp(-$dt / $tau));
    }

    private static function clamp(float $value, float $low, float $high): float
    {
        return max($low, min($high, $value));
    }
}
