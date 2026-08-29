<?php

declare(strict_types=1);

namespace Sdcc\Greenhouse\Tests;

use PHPUnit\Framework\TestCase;
use Sdcc\Greenhouse\Greenhouse;

final class GreenhouseTest extends TestCase
{
    /** A fixed midday timestamp, so the daylight curve is at a known point. */
    private const NOON = 1_787_664_000.0;

    public function test_a_fresh_greenhouse_starts_with_every_actuator_at_rest(): void
    {
        $state = Greenhouse::initial(self::NOON);

        $this->assertSame(
            ['pump' => 'off', 'fan' => 'off', 'vent' => 'off', 'lamp' => 'off', 'door' => 'closed'],
            $state['actuators'],
        );
    }

    public function test_running_the_pump_raises_moisture(): void
    {
        $state = Greenhouse::initial(self::NOON);
        $before = $state['moisture'];

        $state['actuators']['pump'] = 'on';
        $state = Greenhouse::advance($state, self::NOON + 120);

        $this->assertGreaterThan($before, $state['moisture']);
    }

    public function test_moisture_decays_when_the_pump_is_off(): void
    {
        $state = Greenhouse::initial(self::NOON);
        $before = $state['moisture'];

        $state = Greenhouse::advance($state, self::NOON + 1800);

        $this->assertLessThan($before, $state['moisture']);
    }

    public function test_moisture_stays_within_bounds_over_a_long_soak(): void
    {
        $state = Greenhouse::initial(self::NOON);
        $state['actuators']['pump'] = 'on';

        // MAX_STEP caps a single call, so step forward repeatedly.
        for ($i = 1; $i <= 10; $i++) {
            $state = Greenhouse::advance($state, self::NOON + ($i * 3600));
        }

        $this->assertLessThanOrEqual(100.0, $state['moisture']);
        $this->assertGreaterThanOrEqual(0.0, $state['moisture']);
    }

    public function test_the_fan_pulls_temperature_down(): void
    {
        $baseline = Greenhouse::advance(Greenhouse::initial(self::NOON), self::NOON + 1800);

        $cooled = Greenhouse::initial(self::NOON);
        $cooled['actuators']['fan'] = 'on';
        $cooled = Greenhouse::advance($cooled, self::NOON + 1800);

        $this->assertLessThan($baseline['temperature'], $cooled['temperature']);
    }

    public function test_the_vent_pulls_temperature_down(): void
    {
        $baseline = Greenhouse::advance(Greenhouse::initial(self::NOON), self::NOON + 1800);

        $cooled = Greenhouse::initial(self::NOON);
        $cooled['actuators']['vent'] = 'on';
        $cooled = Greenhouse::advance($cooled, self::NOON + 1800);

        $this->assertLessThan($baseline['temperature'], $cooled['temperature']);
    }

    public function test_the_vent_is_a_weaker_cooler_than_the_fan(): void
    {
        $vented = Greenhouse::initial(self::NOON);
        $vented['actuators']['vent'] = 'on';
        $vented = Greenhouse::advance($vented, self::NOON + 1800);

        $fanned = Greenhouse::initial(self::NOON);
        $fanned['actuators']['fan'] = 'on';
        $fanned = Greenhouse::advance($fanned, self::NOON + 1800);

        $this->assertGreaterThan($fanned['temperature'], $vented['temperature']);
    }

    public function test_the_lamp_adds_light(): void
    {
        $state = Greenhouse::initial(self::NOON);
        $dark = Greenhouse::readings($state, self::NOON)['light'];

        $state['actuators']['lamp'] = 'on';
        $lit = Greenhouse::readings($state, self::NOON)['light'];

        $this->assertGreaterThan($dark, $lit);
    }

    public function test_advancing_to_the_same_instant_changes_nothing(): void
    {
        $state = Greenhouse::initial(self::NOON);
        $advanced = Greenhouse::advance($state, self::NOON);

        // dt is zero, so only the deterministic jitter applies.
        $this->assertEqualsWithDelta(
            $state['temperature'],
            $advanced['temperature'],
            0.2,
        );
    }

    public function test_the_model_is_deterministic(): void
    {
        $first = Greenhouse::advance(Greenhouse::initial(self::NOON), self::NOON + 600);
        $second = Greenhouse::advance(Greenhouse::initial(self::NOON), self::NOON + 600);

        $this->assertSame($first, $second);
    }
}
