<?php

namespace App\Services;

class CosineSimilarity
{
    /**
     * Cosseno: (a·b) / (|a| |b|).
     * 1 = mesmo sentido, 0 = ortogonal, -1 = oposto.
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public function score(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valueA) {
            $valueB = $b[$i];
            $dot += $valueA * $valueB;
            $normA += $valueA * $valueA;
            $normB += $valueB * $valueB;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator == 0.0) {
            return 0.0;
        }

        return $dot / $denominator;
    }
}
