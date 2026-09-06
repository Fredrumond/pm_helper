<?php

namespace App\Support;

use Illuminate\Support\Str;

class Versoes
{
    /**
     * Releases do mais recente para o mais antigo.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todas(): array
    {
        return array_values(config('versoes.releases', []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function atual(): ?array
    {
        return static::todas()[0] ?? null;
    }

    public static function numeroAtual(): ?string
    {
        return static::atual()['versao'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function estados(): array
    {
        return config('versoes.estados', []);
    }

    /**
     * Releases com módulos e itens reduzidos ao que casa com os filtros.
     * Um release cujo número, título, resumo ou commit casa com a busca mantém todos os itens.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filtrar(?string $estado = null, string $busca = ''): array
    {
        $busca = trim($busca);

        if ($estado === null && $busca === '') {
            return static::todas();
        }

        $releases = [];

        foreach (static::todas() as $release) {
            $releaseCasa = $busca !== '' && static::contem($busca, ...static::textosDoRelease($release));

            $modulos = [];

            foreach ($release['modulos'] ?? [] as $modulo) {
                $moduloCasa = $releaseCasa || ($busca !== '' && static::contem($busca, $modulo['nome']));

                $itens = array_values(array_filter(
                    $modulo['itens'] ?? [],
                    fn (array $item): bool => (
                        $estado === null || $item['estado'] === $estado
                    ) && (
                        $busca === '' || $moduloCasa || static::contem($busca, $item['titulo'], $item['nota'] ?? null)
                    )
                ));

                if ($itens !== []) {
                    $modulos[] = [...$modulo, 'itens' => $itens];
                }
            }

            if ($modulos !== []) {
                $releases[] = [...$release, 'modulos' => $modulos];
            }
        }

        return $releases;
    }

    /**
     * Quantidade de itens por estado em todos os releases.
     *
     * @return array<string, int>
     */
    public static function contagemPorEstado(): array
    {
        $contagem = array_fill_keys(array_keys(static::estados()), 0);

        foreach (static::itens() as $item) {
            $contagem[$item['estado']] = ($contagem[$item['estado']] ?? 0) + 1;
        }

        return $contagem;
    }

    public static function totalEntregas(): int
    {
        return count(static::itens());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function itens(): array
    {
        $itens = [];

        foreach (static::todas() as $release) {
            foreach ($release['modulos'] ?? [] as $modulo) {
                foreach ($modulo['itens'] ?? [] as $item) {
                    $itens[] = $item;
                }
            }
        }

        return $itens;
    }

    /**
     * @param  array<string, mixed>  $release
     * @return list<string|null>
     */
    protected static function textosDoRelease(array $release): array
    {
        $textos = [
            $release['versao'] ?? null,
            $release['titulo'] ?? null,
            $release['resumo'] ?? null,
            $release['nota'] ?? null,
        ];

        foreach ($release['commits'] ?? [] as $commit) {
            $textos[] = $commit['hash'] ?? null;
            $textos[] = $commit['mensagem'] ?? null;
        }

        return $textos;
    }

    protected static function contem(string $busca, ?string ...$valores): bool
    {
        return Str::contains(implode(' ', array_filter($valores)), $busca, ignoreCase: true);
    }
}
