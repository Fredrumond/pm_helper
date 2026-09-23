# Discovery: Catálogo de modelos de LLM administrável, separado por provedor

## 1. Resumo

Hoje a lista de modelos de LLM que o produto oferece vive hardcoded em `config/chat.php`, misturando modelos de provedores diferentes (OpenRouter e OpenAI) num único array, identificados apenas por um campo `tier` livre (`Free`, `OpenAI`, `Padrão`). Para adicionar, remover ou desativar um modelo, é preciso editar o arquivo e fazer deploy. O preço por token de modelo pago também é fixo em código, na tabela `config/llm.php`.

A demanda cria uma tela administrativa própria para gerenciar esse catálogo: o admin passa a cadastrar, ativar e inativar modelos diretamente pelo sistema, sem depender de código ou deploy. Cada modelo tem um provedor explícito — restrito, por ora, aos dois já integrados tecnicamente ao produto (OpenRouter e OpenAI) — e uma classificação de cobrança (`free` ou `paid`). Para modelo pago, o admin passa a controlar o preço por token diretamente na tela, no lugar da tabela fixa hoje em `config/llm.php`.

## 2. Objetivo

Permitir que o admin controle, por uma tela própria, quais modelos de LLM o produto oferece — cadastrando, ativando e inativando modelos — com cada modelo classificado por provedor e por tipo de cobrança, e com o preço de modelo pago administrável pela própria tela, eliminando a dependência de editar `config/chat.php` e `config/llm.php` e fazer deploy para essa manutenção.

## 3. Escopo

### Dentro

- Tela administrativa nova, restrita a quem já é admin, para gerenciar o catálogo de modelos disponíveis no produto
- Cadastro de modelo novo com os campos: identificador (`id`), nome (`name`), tipo de cobrança (`tier`: `free` ou `paid`) e provedor
- Provedor restrito às integrações já existentes tecnicamente no produto: OpenRouter e OpenAI
- Ativar e inativar um modelo já cadastrado
- Ao inativar um modelo que hoje está definido como o modelo ativo de algum prompt (tela do [Discovery 0007](0007-modelo-de-cada-prompt-pelo-admin.md)), o sistema avisa o admin disso antes de seguir; o admin precisa trocar esse prompt para outro modelo primeiro, e só depois a inativação é concluída
- Para modelo com tier `paid`, o admin define e mantém o preço por token cobrado, diretamente na tela — substitui a tabela fixa hoje em `config/llm.php`
- Migração dos modelos hoje hardcoded em `config/chat.php` (e seus preços em `config/llm.php`, quando pagos) para esse catálogo, como carga inicial
- Este catálogo passa a ser a fonte dos modelos oferecidos nas telas que hoje leem `config/chat.php` (compositor do chat e a tela de modelo por prompt do Discovery 0007) — só modelos ativos aparecem como opção

### Fora

- Escolher qual modelo cada prompt usa (entrevista, retrieval de docs, briefing, geração de card) — isso já é resolvido pela tela do [Discovery 0007](0007-modelo-de-cada-prompt-pelo-admin.md); esta demanda cuida do catálogo em si, não da escolha por prompt
- Cadastrar modelo de um provedor além de OpenRouter e OpenAI — a lista de provedores fica restrita aos já integrados tecnicamente; um provedor novo só entra nesta tela quando a integração técnica dele existir (fora desta demanda)
- Gerenciar chave de API de cada provedor — continua em `.env` / `config/services.php`
- Editar um modelo depois de cadastrado — identificador, nome, tier e provedor ficam fixos após a criação; só ativar/inativar mudam seu estado. Corrigir um cadastro errado exige inativá-lo e cadastrar outro
- Excluir definitivamente (hard delete) um modelo do catálogo — o controle é por ativar/inativar
- Alterar texto ou versão dos prompts
- Auditoria das alterações desta tela (fica coberta pelo mecanismo geral do [Discovery 0008](0008-auditoria-de-mudancas-em-configuracoes-administrativas.md), aplicado ao novo model quando ele existir)

## Fluxo

1. O admin abre a tela de catálogo de modelos.
2. Vê os modelos existentes, organizados por provedor, cada um com seu status (ativo/inativo) e, se pago, o preço por token vigente.
3. Cadastra um modelo novo, informando id, nome, tier (free/paid) e provedor (OpenRouter ou OpenAI).
4. Ativa um modelo, ou tenta inativar um modelo:
   - Se o modelo não é o ativo de nenhum prompt, a inativação é concluída.
   - Se o modelo é o ativo de algum prompt, o sistema avisa o admin e pede para trocar esse prompt para outro modelo antes de inativar.
5. Para um modelo pago, o admin define ou ajusta o preço por token diretamente na tela.
6. A partir da mudança, as telas que oferecem modelo para escolha (compositor do chat, tela de modelo por prompt) passam a considerar apenas os modelos ativos deste catálogo.

## 4. Premissas

- Só modelo de provedor já integrado tecnicamente ao produto (hoje OpenRouter e OpenAI) pode ser cadastrado; não existe cadastro de provedor "de nome" sem a integração técnica correspondente
- Este catálogo substitui a lista de `models` hoje hardcoded em `config/chat.php` e a tabela de preço hoje fixa em `config/llm.php` para os modelos pagos; as entradas atuais entram como carga inicial, sem perda de modelo ou preço já vigente
- Só quem já tem o papel de admin acessa e altera esta tela, no mesmo padrão das demais telas administrativas do produto
- Inativar um modelo não é o mesmo que excluí-lo: o registro permanece no catálogo, apenas deixa de aparecer como opção nas telas que o consomem
- Identificador, nome, tier e provedor de um modelo são definidos só no cadastro e não mudam depois; o preço por token de modelo pago é a única informação que o admin continua ajustando após o cadastro
- O modelo padrão do `.env` (`OPENROUTER_MODEL`) deixa de entrar automaticamente na lista de modelos oferecidos; ele só participa do catálogo se o admin o cadastrar explicitamente
- Esta tela não decide qual modelo cada prompt usa — essa responsabilidade continua com a tela do Discovery 0007

## 5. Considerações de segurança

- Dados sensíveis: o catálogo trata de identificador técnico, nome, tier, provedor e preço por token do modelo. Não envolve chave de API, credencial ou dado de cliente final
- Autenticação/Autorização: só o admin cadastra, ativa, inativa e ajusta preço de modelo; nenhum outro papel altera esta tela
- Exposição de APIs: não aplicável — não há endpoint novo exposto fora do produto
- Compliance: não aplicável
- Outras considerações: nenhuma outra identificada

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

Campos de cadastro, imutabilidade após criação, restrição de provedor aos já integrados, o aviso e a exigência de trocar o modelo do prompt antes de inativar, e o controle administrável do preço de modelo pago estão decididos.
