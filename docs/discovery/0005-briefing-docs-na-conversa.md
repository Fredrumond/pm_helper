# Discovery: Briefing de `/docs` na conversa antes de gerar o card

## 1. Resumo

Hoje, ao encerrar a entrevista, o PM Helper faz o retrieval de `/docs` (Discovery 0004) e exibe apenas uma frase como "Revisão carregada (2 arquivos). Você já pode gerar o card." O conteúdo lido fica invisível até a geração do card. A demanda é adicionar um step de briefing: após ler os arquivos selecionados, um prompt versionado cruza o conteúdo de `/docs` com o resumo da entrevista e gera uma mensagem humanizada na conversa, destacando sobreposições, conflitos e vocabulário do produto. O PM recebe os fatos antes de clicar em **Gerar Card**, sem precisar abrir o repositório GitHub.

## 2. Objetivo

Tornar visível ao PM o que o retrieval encontrou em `/docs` — especialmente quando os docs indicam que a demanda já existe ou conflita com algo existente — para que ele possa ajustar o contexto antes de gerar o card.

## 3. Escopo

### Dentro

- Novo step `docs_briefing` executado após `readDocsByPaths` (ou após `readProjectDocs` no fallback), antes de persistir a mensagem de revisão na conversa
- Novo prompt versionado `resources/prompts/docs_briefing/v1.md` (entrada: conteúdo dos arquivos lidos + resumo da entrevista; saída: texto humanizado em português, expondo sobreposições, conflitos e vocabulário do produto — sem perguntas ao PM)
- Registro no `SystemPromptCatalog` e em `config/chat.php` (chave `docs_briefing`)
- Modelo configurável via `CHAT_DOCS_BRIEFING_MODEL` (padrão: mesmo modelo do `card_generation`)
- Sem limite de tamanho imposto ao texto gerado pelo briefing
- Comportamento na conversa: a frase "Revisão carregada (N arquivos). Você já pode gerar o card." é mantida; o briefing é acrescentado a seguir como mensagem separada do assistente
- Fallback: se o briefing falhar (LLM, timeout, parse) ou retornar vazio, apenas a frase atual é exibida — sem mensagem de erro ao PM
- Telemetria: logar `project_docs.briefing` com `status`, `chars_input` e `chars_output`
- O briefing só ocorre quando `ProjectDocsResult::STATUS_OK`

### Fora

- Alteração no `card_generation@v3` — o conteúdo dos docs continua sendo injetado via bloco separado, sem modificação
- Armazenamento do briefing no banco de dados ou na sessão além da `Message` criada
- Atualização automática do `interview_summary` com base na resposta do PM ao briefing
- Comportamento para o PM responder ao briefing e ter o card adaptado (requer change separado em `interview_summary` e/ou `card_generation`)
- Briefing para status `empty`, `too_large` ou `failed` — nesses casos a frase atual é suficiente
- Cache ou reutilização do briefing em retries (retry relê `/docs` e gera novo briefing)
- Perguntas ao PM no texto do briefing — o prompt deve apenas expor os fatos

## 4. Premissas

- Feature flag: **Não** — entrega direta, sem toggle
- O briefing só ocorre quando `ProjectDocsResult::STATUS_OK`; os demais status mantêm o comportamento atual
- O conteúdo dos arquivos lidos está disponível em `ProjectDocsResult::$content` — sem nova chamada ao GitHub
- O briefing é texto livre para humano, não JSON estruturado; o fallback para a frase atual é a rede de segurança de parse
- Modelo do briefing: mesmo modelo do `card_generation`, configurável via `CHAT_DOCS_BRIEFING_MODEL`
- Sem limite de tamanho imposto ao texto gerado; o prompt instrui o modelo a ser objetivo, mas não impõe teto de chars ou linhas
- O briefing vai como mensagem `assistant` e entra no histórico enviado ao `interview@v4` nas próximas chamadas de entrevista
- O complemento do PM após o briefing hoje entra no `chat()` com `interview@v4` e não atualiza `interview_summary` — limitação conhecida, fora do escopo desta entrega

## 5. Considerações de segurança

- **Dados sensíveis:** o conteúdo de `/docs` (já enviado ao LLM no `card_generation`) agora também alimenta o briefing — mesma superfície de exposição do Discovery 0003/0004; sem risco incremental
- **Autenticação/Autorização:** sem alteração — o briefing usa o conteúdo já lido, não relê o GitHub
- **Exposição de APIs:** nenhum endpoint novo; step interno de LLM, sem tráfego adicional ao GitHub
- **Compliance:** mesmas considerações de LGPD do Discovery 0003 — `/docs` pode conter dados de produto; responsabilidade do time garantir que a pasta seja segura para tráfego externo
- **Prompt injection:** o conteúdo de `/docs` é dado de usuário indireto; o prompt de briefing deve tratar como entrada, nunca como instrução

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento? Sim**

Objetivo, escopo, ponto de injeção (`DocsRetrievalService`), modelo, tamanho, comportamento na conversa e fallback estão definidos. Sem lacunas em aberto.
