<?php

namespace Tests\Unit\Support;

use App\Support\Versoes;
use Tests\TestCase;

class VersoesTest extends TestCase
{
    public function test_lists_releases_newest_first_and_tracks_the_first_commit(): void
    {
        $versoes = array_column(Versoes::todas(), 'versao');

        $this->assertSame('0.5.9', $versoes[0]);
        $this->assertContains('0.1.0', $versoes);
        $this->assertSame('0.5.9', Versoes::numeroAtual());
        $this->assertSame('ADR da arquitetura LLM', Versoes::atual()['titulo']);
        $this->assertGreaterThan(0, Versoes::totalEntregas());
        $this->assertSame(['stable', 'development', 'test', 'bug'], array_keys(Versoes::contagemPorEstado()));
    }

    public function test_search_matches_commit_hash_and_keeps_the_whole_release(): void
    {
        $releases = Versoes::filtrar(null, '39138ba');

        $this->assertSame(['0.1.0'], array_column($releases, 'versao'));
        $this->assertSame('Integração OpenRouter', $releases[0]['modulos'][4]['itens'][0]['titulo']);
    }

    public function test_search_matches_commit_message(): void
    {
        $releases = Versoes::filtrar(null, 'rate limit');

        $this->assertSame(['0.3.0'], array_column($releases, 'versao'));
    }

    public function test_filter_by_estado_keeps_only_matching_items(): void
    {
        $releases = Versoes::filtrar('bug');

        $this->assertSame(['0.5.8', '0.5.6'], array_column($releases, 'versao'));
        $this->assertSame('MiniMax M3 removido do catálogo e do default', $releases[0]['modulos'][0]['itens'][0]['titulo']);
        $this->assertCount(1, $releases[0]['modulos'][0]['itens']);

        $development = Versoes::filtrar('development', 'first commit');

        $this->assertSame([], $development);
    }
}
