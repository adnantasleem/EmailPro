<?php

namespace App\Services;

class DelayManagerService
{
    /**
     * Calculate a random delay between min and max seconds.
     */
    public function calculateDelay(int $minSeconds, int $maxSeconds): int
    {
        if ($minSeconds >= $maxSeconds) {
            return $minSeconds;
        }

        return rand($minSeconds, $maxSeconds);
    }

    /**
     * Apply delay (sleep) and return the actual seconds waited.
     */
    public function applyDelay(int $minSeconds, int $maxSeconds): int
    {
        $delay = $this->calculateDelay($minSeconds, $maxSeconds);
        
        if ($delay > 0) {
            sleep($delay);
        }

        return $delay;
    }

    /**
     * Calculate delay with some randomness for more human-like behavior.
     * Adds millisecond variations.
     */
    public function applyDelayWithVariation(int $minSeconds, int $maxSeconds): float
    {
        $baseDelay = $this->calculateDelay($minSeconds, $maxSeconds);
        
        // Add random milliseconds (0-999)
        $milliseconds = rand(0, 999) / 1000;
        $totalDelay = $baseDelay + $milliseconds;

        if ($totalDelay > 0) {
            usleep((int)($totalDelay * 1000000));
        }

        return $totalDelay;
    }
}
