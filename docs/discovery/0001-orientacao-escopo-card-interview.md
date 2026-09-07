# Discovery: Orientar PM sobre escopo de card antes e durante o Interview Helper

## 1. Resumo

O Assistente de Interview trata qualquer necessidade descrita pelo PM como um único card, sem orientar que a entrevista cobre um card por vez nem que o épico deve estar definido de antemão. Na tela inicial, a copy atual (“Descreva uma necessidade ou ideia…”) não deixa esse limite explícito. Durante a entrevista, o agente segue o fluxo até o encerramento que libera a geração do card, mesmo quando a demanda equivale a um épico com vários cards. A regra de negócio desta entrega é sinalizar esse limite de forma proativa — na tela inicial e durante a entrevista — e, quando o escopo for amplo demais, orientar o retorno com um card mais definido, sem gerar card mal definido.

## 2. Objetivo

Reduzir a geração de cards mal definidos ou de épicos disfarçados de card, deixando claro para o PM que cada entrevista cobre um único card, com o épico previamente definido, e interrompendo o caminho de geração quando o escopo descrito for amplo demais.

## 3. Escopo

### Dentro

- Nova copy na tela inicial do Assistente de Interview (estado sem mensagens, antes de iniciar a conversa), deixando claro que a entrevista cobre um card por vez e que o épico deve estar previamente definido pelo PM. A redação pode ser expandida a partir do exemplo informado (“Esta entrevista cobre um único card. Defina o épico antes de começar.”), desde que esse limite permaneça explícito.
- Comportamento do agente durante a entrevista: ao detectar escopo equivalente a um épico com vários cards, sinalizar que o escopo não cabe em um único card, recomendar que o PM volte com o card mais definido e encerrar a entrevista sem geração de card.
- Persistência da recomendação se o PM insistir em continuar após o aviso: o agente não avança para a geração do card e pede retomada quando o escopo estiver melhor delimitado.

### Fora

- Sugerir ferramentas externas ao PM para quebrar o épico em cards.
- Bloquear o PM de digitar ou de iniciar a conversa.
- Gerar múltiplos cards a partir de um único discovery.
- Forçar a geração de um card mal definido quando o escopo for amplo.
- Orientação de escopo em outras telas do produto (geração de card, preview, métricas, etc.).
- Controle da demanda por feature flag.

## 4. Premissas

- Feature flag: Não
- Um discovery/entrevista corresponde a um único card.
- O épico é responsabilidade do PM e deve estar definido antes da entrevista; o assistente não quebra épico em cards.
- “Encerrar a entrevista” por escopo amplo não é o encerramento feliz que libera a geração do card. Resultado esperado: nenhum card gerado.
- O PM continua podendo digitar e iniciar a conversa; o limite é comportamental (orientação + recusa de seguir para geração), não um bloqueio de interface para começar.
- A copy da tela inicial pode ser expandida com base no exemplo já informado; o exemplo é a base, não um texto congelado palavra por palavra.
- O critério de “escopo amplo” nesta entrega é o exemplo de aceite (“fluxo completo de onboarding com KYC, abertura de conta e primeiro investimento”, equivalente a um épico com vários cards). Não há outras fronteiras definidas para citar agora.

## 5. Considerações de segurança

- Dados sensíveis: Não aplicável — a demanda não introduz novo tipo de dado; segue o conteúdo já existente das conversas de entrevista.
- Autenticação/Autorização: Sem mudança de requisitos; o Assistente de Interview permanece no fluxo autenticado já existente.
- Exposição de APIs: Não aplicável — sem endpoints novos ou alteração de exposição descrita nesta demanda.
- Compliance: Não aplicável — sem mudança de tratamento de dados ou de requisito de compliance.
- Outras considerações: Nenhuma identificada.

Não identificadas considerações de segurança relevantes para esta demanda.

## 6. Dúvidas

- Nenhuma em aberto. As duas dúvidas anteriores foram fechadas por Frederico Drumond: (1) a copy pode ser expandida a partir do exemplo informado; (2) não há outras fronteiras de “escopo amplo” para citar nesta entrega.

## 7. Informações ausentes

- Nenhuma. A redação da tela inicial parte do exemplo e pode ser expandida. A detecção de escopo amplo usa o exemplo de aceite; fronteiras adicionais ficam de fora até existirem.

## 8. Status

**Pronto para Planejamento?** Sim

Objetivo, escopo (dentro/fora), aceite, feature flag e as dúvidas de copy e de critério de escopo amplo estão fechados.
