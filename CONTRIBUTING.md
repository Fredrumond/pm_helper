# Como contribuir

Obrigado pelo interesse no PM Helper. Issues e pull requests são bem-vindos.

Ao participar, você concorda com o [Código de Conduta](CODE_OF_CONDUCT.md).

## Antes de abrir um pull request

1. Confira se já existe uma [issue](https://github.com/Fredrumond/pm_helper/issues) sobre o assunto.
2. Descreva o problema ou a mudança que você quer fazer. Para algo grande, abra a issue antes de codar.
3. Faça o fork, crie um branch a partir de `main` e mantenha o diff focado em uma mudança.

## Ambiente

O setup está no [README](README.md). PHP, Artisan e Composer rodam dentro do container:

```bash
docker compose exec app php artisan …
docker compose exec app composer …
```

Não commite `.env`, chaves de API nem dumps de banco.

## Convenções do código

- Prompts ficam em `resources/prompts/<passo>/v<n>.md`. Para mudar o comportamento, crie uma versão nova (`v2.md`, `v3.md`…) e registre em `SystemPromptCatalog`. Não edite a versão anterior.
- Migrations novas saem de `php artisan make:migration`. Não altere uma migration que já rodou.
- Adapter novo de LLM grava `LlmUsage`. Se a API não devolver `usage.cost`, cadastre o preço em `config/llm.php`.
- Decisões de arquitetura vão em `docs/adr/`.

## Testes

Rode a suíte dentro do container antes de abrir o pull request:

```bash
docker compose exec app composer test
```

Para um arquivo ou filtro:

```bash
docker compose exec app php artisan test --filter=NomeDoTeste
```

## Pull request

- Explique o porquê da mudança e como você testou.
- Inclua o issue relacionado, se houver.
- O título deve dizer o efeito da mudança (por exemplo: "corrige o parse do card quando o JSON vem cercado de texto").
