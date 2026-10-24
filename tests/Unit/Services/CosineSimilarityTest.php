<?php

namespace Tests\Unit\Services;

use App\Services\CosineSimilarity;
use Tests\TestCase;

class CosineSimilarityTest extends TestCase
{
    private CosineSimilarity $cosine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cosine = new CosineSimilarity;
    }

    public function test_identical_vectors_score_one(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->cosine->score([1.0, 0.0], [1.0, 0.0]), 0.0001);
    }

    public function test_same_direction_different_magnitude_still_scores_one(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->cosine->score([2.0, 0.0], [5.0, 0.0]), 0.0001);
    }

    public function test_orthogonal_vectors_score_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->cosine->score([1.0, 0.0], [0.0, 1.0]), 0.0001);
    }

    public function test_opposite_vectors_score_minus_one(): void
    {
        $this->assertEqualsWithDelta(-1.0, $this->cosine->score([1.0, 0.0], [-1.0, 0.0]), 0.0001);
    }

    public function test_known_angle_between_axis_and_diagonal(): void
    {
        $this->assertEqualsWithDelta(1 / sqrt(2), $this->cosine->score([1.0, 0.0], [1.0, 1.0]), 0.0001);
    }

    public function test_zero_or_mismatched_vectors_score_zero(): void
    {
        $this->assertSame(0.0, $this->cosine->score([0.0, 0.0], [1.0, 0.0]));
        $this->assertSame(0.0, $this->cosine->score([1.0], [1.0, 0.0]));
        $this->assertSame(0.0, $this->cosine->score([], [1.0]));
    }
}
