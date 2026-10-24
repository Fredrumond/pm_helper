<?php

/**
 * POC isolada: dispara um Cloud Agent do Cursor num repositório e imprime o discovery.
 *
 * Uso:
 *   php scripts/poc_cursor_discovery.php [repo_url] [branch] [nome_projeto]
 *
 * A pasta de saída é o projeto-alvo do agente (repo em que ele roda), nunca
 * o nome deste repositório pm_helper só por abrigar o script. Ordem:
 *   3º argumento → repos[0].url devolvido pelo agente → URL pedida na CLI.
 *
 * Lê CURSOR_API_KEY do ambiente ou do .env na raiz.
 *
 * Skill: resources/agent_skills/discovery/SKILL.md
 *   (fora de .cursor/skills — a IDE não carrega; só o pm_helper injeta no prompt)
 *
 * A API do Cursor não tem campo para "anexar skill" por id. O Cloud Agent só
 * carrega skills que estejam no próprio repo (.cursor/skills/) ou skills
 * pessoais sincronizadas (Sync Skills for Cloud Agents). Nesta POC injetamos
 * o SKILL.md do catálogo no prompt e pedimos o Markdown de volta no result
 * (a skill original grava em docs/discovery/ — isso conflitava com só-leitura).
 *
 * Opcionais: CURSOR_MODEL, POLL_SECONDS (10), TIMEOUT_MINUTES (20).
 */
const API_BASE = 'https://api.cursor.com/v1';

if (isMainScript($argv[0] ?? null)) {
    exit(main($argv));
}

function isMainScript(?string $argv0): bool
{
    if ($argv0 === null) {
        return false;
    }

    $invoked = realpath($argv0);

    return $invoked !== false && $invoked === realpath(__FILE__);
}

function main(array $argv): int
{
    loadDotEnv(__DIR__.'/../.env');

    $apiKey = trim((string) getenv('CURSOR_API_KEY'));
    if ($apiKey === '') {
        fwrite(STDERR, "Defina CURSOR_API_KEY (Cursor Dashboard → Integrations / API Keys).\n");

        return 1;
    }

    $repoUrl = $argv[1] ?? 'https://github.com/Fredrumond/pm_helper';
    $branch = $argv[2] ?? 'develop';
    $projectNameArg = trim((string) ($argv[3] ?? ''));
    $model = trim((string) getenv('CURSOR_MODEL'));
    $pollSeconds = max(3, (int) (getenv('POLL_SECONDS') ?: 10));
    $timeoutSeconds = 60 * max(1, (int) (getenv('TIMEOUT_MINUTES') ?: 20));

    $card = [
        'title' => 'Avisar quando um card novo parece duplicado de um existente',
        'objetivo' => 'Ao gerar um card, o PM deve ser avisado se já existe card parecido no mesmo projeto, para não abrir demanda repetida.',
        'como_funciona_hoje' => 'O card é gerado ao fim da entrevista e salvo sem nenhuma comparação com cards anteriores.',
        'onde' => ['Tela de preview do card', 'Geração do card a partir da conversa'],
        'aceite' => [
            'Ao gerar o card, mostrar até 3 cards parecidos do mesmo projeto, com link.',
            'O PM pode seguir mesmo assim.',
        ],
    ];

    $skillPath = resolveSkillPath();
    if ($skillPath === null) {
        fwrite(STDERR, "Skill de discovery não encontrada: resources/agent_skills/discovery/SKILL.md\n");

        return 1;
    }

    $prompt = buildPrompt($card, $skillPath);
    echo "Skill: {$skillPath}\n";
    echo "Repositório alvo: {$repoUrl} ({$branch})\n";
    echo "Criando agente...\n";

    $body = [
        'name' => 'Discovery: '.mb_substr($card['title'], 0, 80),
        'prompt' => ['text' => $prompt],
        'repos' => [['url' => $repoUrl, 'startingRef' => $branch]],
        'autoCreatePR' => false,
    ];
    if ($model !== '') {
        $body['model'] = ['id' => $model];
    }

    $created = request('POST', '/agents', $apiKey, $body);
    $agentId = $created['agent']['id'] ?? null;
    $runId = $created['run']['id'] ?? null;

    if (! $agentId || ! $runId) {
        fwrite(STDERR, "Resposta inesperada ao criar agente:\n" . json_encode($created, JSON_PRETTY_PRINT) . "\n");

        return 1;
    }

    $agentRepoUrl = (string) ($created['agent']['repos'][0]['url'] ?? $repoUrl);
    $projectSlug = $projectNameArg !== ''
        ? slugify($projectNameArg)
        : projectSlugFromRepoUrl($agentRepoUrl);

    echo "Projeto (pasta do discovery): {$projectSlug}\n";
    echo "Agente: {$agentId}\n";
    echo "Run:    {$runId}\n";
    echo 'Acompanhe em: ' . ($created['agent']['url'] ?? "https://cursor.com/agents/{$agentId}") . "\n\n";

    $terminal = ['FINISHED', 'ERROR', 'CANCELLED', 'EXPIRED'];
    $started = time();
    $lastStatus = null;
    $run = [];

    while (true) {
        $run = request('GET', "/agents/{$agentId}/runs/{$runId}", $apiKey);
        $status = $run['status'] ?? 'UNKNOWN';

        if ($status !== $lastStatus) {
            printf("[%3ds] status: %s\n", time() - $started, $status);
            $lastStatus = $status;
        }

        if (in_array($status, $terminal, true)) {
            break;
        }

        if (time() - $started > $timeoutSeconds) {
            fwrite(STDERR, "Timeout. O agente continua rodando; veja no link acima.\n");

            return 3;
        }

        sleep($pollSeconds);
    }

    $duration = isset($run['durationMs']) ? round($run['durationMs'] / 1000) . 's' : '?';
    echo "\nTerminou com {$status} em {$duration}.\n";

    if (! empty($run['git']['branches'])) {
        echo "Branch de trabalho reservada pelo Cursor (só existe no GitHub se o agente tiver feito push):\n";
        foreach ($run['git']['branches'] as $b) {
            $pr = isset($b['prUrl']) ? " PR: {$b['prUrl']}" : '';
            echo '  - ' . ($b['repoUrl'] ?? '?') . ' ' . ($b['branch'] ?? '?') . "{$pr}\n";
        }
        echo "  Confira com: git ls-remote --heads origin 'cursor/*'\n";
    }

    $result = (string) ($run['result'] ?? '');
    if ($result === '') {
        fwrite(STDERR, "Sem texto de resultado.\n" . json_encode($run, JSON_PRETTY_PRINT) . "\n");

        return 2;
    }

    $outDir = __DIR__ . '/../storage/app/discoveries/' . $projectSlug;
    if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
        fwrite(STDERR, "Não foi possível criar {$outDir}\n");

        return 1;
    }
    $outFile = discoveryOutputPath($outDir, $result, $card['title']);
    file_put_contents($outFile, $result);

    echo "\n========== DISCOVERY ==========\n\n{$result}\n\n";
    echo 'Salvo em: ' . (realpath($outFile) ?: $outFile) . "\n";

    return $status === 'FINISHED' ? 0 : 2;
}

function projectSlugFromRepoUrl(string $repoUrl): string
{
    // Aceita https://github.com/org/repo ou github.com/org/repo (sem scheme).
    $normalized = preg_match('#^https?://#i', $repoUrl) ? $repoUrl : 'https://' . ltrim($repoUrl, '/');
    $path = parse_url($normalized, PHP_URL_PATH) ?: $normalized;
    $base = basename(rtrim((string) $path, '/'));
    $slug = preg_replace('/\.git$/i', '', $base) ?: 'projeto';

    return slugify((string) $slug);
}

/**
 * Nome legível: H1 do Markdown do discovery, senão o título do card.
 * Se o arquivo já existir, acrescenta data/hora para não sobrescrever.
 */
function discoveryOutputPath(string $outDir, string $markdown, string $fallbackTitle): string
{
    $slug = slugify(discoveryTitleFromMarkdown($markdown) ?? $fallbackTitle);
    $path = $outDir.'/'.$slug.'.md';

    if (! is_file($path)) {
        return $path;
    }

    return $outDir.'/'.$slug.'-'.date('Ymd_His').'.md';
}

function discoveryTitleFromMarkdown(string $markdown): ?string
{
    if (preg_match('/^#\s+(.+?)\s*$/m', $markdown, $matches) !== 1) {
        return null;
    }

    $title = trim($matches[1]);

    return $title !== '' ? $title : null;
}

function slugify(string $value): string
{
    $slug = mb_strtolower(trim($value), 'UTF-8');
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
    if (is_string($transliterated) && $transliterated !== '') {
        $slug = strtolower($transliterated);
    }
    $slug = (string) preg_replace('/[^a-z0-9._-]+/', '-', $slug);

    return trim($slug, '-') ?: 'discovery';
}

function resolveSkillPath(): ?string
{
    $path = dirname(__DIR__) . '/resources/agent_skills/discovery/SKILL.md';
    $real = realpath($path) ?: $path;

    return is_readable($real) ? $real : null;
}

function buildPrompt(array $card, string $skillPath): string
{
    $skillBody = file_get_contents($skillPath);
    if ($skillBody === false || trim($skillBody) === '') {
        fwrite(STDERR, "Skill vazia ou ilegível: {$skillPath}\n");
        exit(1);
    }

    $list = fn (array $items) => implode("\n", array_map(fn ($i) => "- {$i}", $items));

    $cardBlock = <<<CARD
## Demanda (card do PM Helper)

Título: {$card['title']}
Objetivo: {$card['objetivo']}
Como funciona hoje (segundo o PM): {$card['como_funciona_hoje']}
Onde (segundo o PM):
{$list($card['onde'])}
Critérios de aceite:
{$list($card['aceite'])}
CARD;

    return <<<PROMPT
Use a skill de discovery abaixo nesta codebase. Explore o código real do repositório para embasar o documento.

OVERRIDE OBRIGATÓRIO (Cloud Agent via API — prevalece sobre a skill):
- Somente leitura. NÃO edite, crie ou apague arquivos. NÃO faça commit, push nem abra PR.
- NÃO grave em docs/discovery/ (nem em qualquer outro path do repo).
- Devolva o documento Markdown completo do Discovery na resposta final (a API só captura esse texto).
- Pode e deve citar arquivos/classes reais do repositório nas seções pertinentes.
- Responda em português.

{$cardBlock}

---

# Skill carregada de {$skillPath}

{$skillBody}
PROMPT;
}

function loadDotEnv(string $path): void
{
    if (! is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if ($name !== '' && getenv($name) === false) {
            putenv("{$name}={$value}");
        }
    }
}

function request(string $method, string $path, string $apiKey, ?array $body = null): array
{
    $ch = curl_init(API_BASE . $path);
    $headers = ['Accept: application/json'];

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $apiKey . ':',
        CURLOPT_TIMEOUT => 60,
    ]);

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        fwrite(STDERR, "Falha de rede em {$method} {$path}: {$error}\n");
        exit(1);
    }

    $data = json_decode($raw, true);
    if ($code >= 400) {
        fwrite(STDERR, "HTTP {$code} em {$method} {$path}:\n{$raw}\n");
        exit(1);
    }

    return is_array($data) ? $data : [];
}
