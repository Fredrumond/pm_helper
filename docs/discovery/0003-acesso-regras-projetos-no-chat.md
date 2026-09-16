# Discovery: Verificação de informações na pasta `/docs` do projeto via MCP do GitHub

## 1. Resumo

O PM Helper já permite que o PM selecione um projeto GitHub antes de iniciar uma conversa (via `SelectProjects` + `CurrentProject`). A demanda é fazer com que o agente, ao encerrar a entrevista e antes de gerar o card, busque e utilize o conteúdo da pasta `/docs` daquele repositório como contexto de revisão final. Isso garante que o card gerado esteja alinhado às regras e informações específicas do projeto. Quando nenhum projeto está selecionado, o agente opera com informações gerais (comportamento atual, sem alteração).

## 2. Objetivo

Enriquecer o contexto de geração de cards com informações do repositório do projeto selecionado, lendo a pasta `/docs` via MCP do GitHub no momento em que a entrevista é concluída, para que o card gerado respeite as regras e convenções daquele produto específico.

## 3. Escopo

### Dentro

- Leitura de todos os arquivos da pasta `/docs` do repositório GitHub associado ao projeto selecionado, de forma recursiva (incluindo subpastas)
- A leitura ocorre no momento em que a entrevista é concluída, imediatamente antes da geração do card — não ao iniciar a conversa
- Uso desse conteúdo como contexto adicional na geração do card
- Comportamento para projeto selecionado sem pasta `/docs` ou com pasta vazia: agente notifica que não há informações adicionais, oferece ação para tentar a revisão novamente e permite seguir com a geração do card
- Comportamento para falha na leitura do GitHub: agente notifica o PM que não foi possível realizar a revisão final e oferece uma ação para que o PM tente novamente antes de prosseguir
- Comportamento para conteúdo de `/docs` excessivamente grande: leitura abortada, log registrado no servidor, PM notificado que a revisão final não pôde ser realizada
- Comportamento para conversa sem projeto selecionado: agente opera sem leitura do GitHub (sem alteração no fluxo atual)

### Fora

- Alteração na interface de seleção de projetos (já implementada)
- Leitura de outras pastas ou arquivos do repositório além de `/docs`
- Escrita ou criação de arquivos no repositório
- Sincronização automática ou cache persistente do conteúdo da pasta `/docs`
- Histórico ou auditoria de leituras realizadas no GitHub
- Leitura da pasta `/docs` em momentos anteriores ao encerramento da entrevista (ex: ao iniciar a conversa ou em cada mensagem)

## 4. Premissas

- Feature flag: **Não**
- O projeto selecionado já está disponível em `CurrentProject::id()` via sessão — ponto único de leitura para o agente
- A autenticação com o GitHub segue o modelo GitHub App definido no Discovery 0002 (App ID + Private Key em variáveis de ambiente; installation token efêmero)
- A pasta pesquisada é sempre `/docs` (caminho fixo) — não é configurável por projeto nesta entrega
- Todos os tipos de arquivo dentro de `/docs` devem ser lidos, sem filtragem por extensão, de forma **recursiva** (incluindo subpastas)
- Se o conteúdo total de `/docs` ultrapassar o limite de tamanho aceitável para o contexto do LLM, a leitura deve ser **abortada**: o agente registra log do ocorrido e notifica o PM que não foi possível realizar a revisão final
- A ausência de pasta `/docs` ou pasta vazia não é erro bloqueante — o agente notifica, oferece retry e permite seguir para a geração do card
- Falha na leitura do GitHub (token expirado, rate limit, timeout, etc.) não é bloqueante, mas o PM deve ser notificado e ter a opção de tentar novamente antes de seguir para a geração do card
- O conteúdo de `/docs` é utilizado apenas como leitura de contexto; nenhuma persistência no banco de dados é necessária

## 5. Considerações de segurança

- **Dados sensíveis:** O conteúdo da pasta `/docs` será enviado ao LLM como contexto. Se o repositório contiver informações confidenciais nesses arquivos (credenciais, dados internos, PII), elas serão expostas ao provedor de LLM. É responsabilidade do time garantir que `/docs` contenha apenas documentação de produto segura para tráfego externo
- **Autenticação/Autorização:** O acesso ao GitHub usa o installation token do GitHub App (já definido no Discovery 0002). O PM não precisa se autenticar no GitHub individualmente. A autorização de quais repositórios o App pode acessar é controlada pela configuração do GitHub App
- **Exposição de APIs:** A chamada ao GitHub API (leitura de árvore de arquivos e conteúdo de `/docs`) ocorre no servidor, nunca no cliente. O `owner/repo` armazenado no `Project` deve ser validado antes do uso para mitigar SSRF
- **Compliance:** Se o repositório pertencer a organização que processa dados pessoais, o envio de conteúdo de `/docs` ao LLM deve ser avaliado conforme LGPD. Fora do escopo desta entrega, mas o time deve estar ciente
- **Outras considerações:** O installation token tem validade de 1 hora (gerenciado pelo GitHub). A falha na renovação do token deve ser tratada com log e fallback gracioso (PM é notificado e pode tentar novamente)

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento? Sim**

A infraestrutura de seleção de projeto e autenticação GitHub App já existe. Escopo, cenários de aceite, momento de leitura, recursividade, comportamento de falha e comportamento para conteúdo excessivo estão todos definidos. Sem lacunas em aberto.
