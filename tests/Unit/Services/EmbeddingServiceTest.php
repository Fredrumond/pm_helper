<?php

namespace Tests\Unit\Services;

use App\Services\EmbeddingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_sends_text_to_openai_and_returns_the_vector(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.21, -0.43, 0.72]],
                ],
            ], 200),
        ]);

        $vector = (new EmbeddingService)->embed('Laravel usa Eloquent');

        $this->assertSame([0.21, -0.43, 0.72], $vector);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/embeddings'
                && $request->hasHeader('Authorization', 'Bearer sk-test')
                && $request['model'] === EmbeddingService::MODEL
                && $request['input'] === 'Laravel usa Eloquent';
        });
    }

    public function test_fails_without_api_key(): void
    {
        config(['services.openai.api_key' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        (new EmbeddingService)->embed('texto');
    }
}
