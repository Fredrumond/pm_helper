# Discovery: Responder às dúvidas do Discovery e regenerar com as respostas

## 1. Resumo

Com o [Discovery 0010](0010-discovery-por-agente-em-card-aprovado.md), o dono de um card aprovado gera um Discovery por agente e o lê renderizado nos anexos do card. O documento gerado pela skill tem uma seção de dúvidas acionáveis, mas hoje o PM não tem como respondê-las dentro do produto: a única saída seria editar o card e gerar de novo, perdendo a ligação entre a resposta e a dúvida.

A demanda permite que o dono do card responda às dúvidas ao lado do Discovery anexado e peça uma nova geração. O agente recebe o card, o Discovery anterior e as respostas, e devolve um documento atualizado — dúvidas resolvidas viram premissas e o status pode avançar — que substitui o anterior no anexo.

## 2. Objetivo

Fechar o ciclo do Discovery dentro do produto: o PM responde às dúvidas levantadas pelo agente e obtém um Discovery atualizado, sem reescrever o card nem perder o contexto de qual resposta pertence a qual dúvida.

## 3. Escopo

### Dentro

- Na leitura do Discovery anexado, cada dúvida da seção de dúvidas aparece com um campo de resposta
- O dono do card pode responder todas ou só parte das dúvidas
- Ação "Gerar de novo com respostas", disponível só para o dono do card e só quando há ao menos uma resposta preenchida
- A nova geração envia ao agente o card, o Discovery anterior e as respostas, cada uma ligada à sua dúvida
- O Discovery atualizado substitui o anterior no anexo, com os mesmos estados do 0010 (solicitado/em andamento, concluído, falhou)
- Se a nova geração falhar, as respostas preenchidas não se perdem e o PM pode tentar de novo

### Fora

- Responder às informações ausentes do Discovery — só a seção de dúvidas ganha campo de resposta
- Conversa livre com o agente (perguntas abertas, pedidos de ajuste fora das dúvidas)
- Continuar a mesma sessão no provedor de agente — cada geração é um pedido novo, para manter o provedor trocável
- Histórico de versões do Discovery ou das respostas anteriores
- Editar o texto do Discovery à mão
- Responder às dúvidas por quem não é dono do card (inclusive admin)
- Notificação fora da tela do card quando a nova geração termina

## Fluxo

1. O dono abre o Discovery concluído nos anexos do card.
2. Preenche a resposta de uma ou mais dúvidas.
3. Clica em "Gerar de novo com respostas"; o card avisa que foi solicitado e passa a em andamento.
4. O agente gera o Discovery atualizado considerando as respostas.
5. Concluído: o novo Discovery substitui o anterior no anexo; as dúvidas que sobrarem aparecem de novo com campo de resposta.
6. Falhou: o card mostra a falha, o Discovery anterior e as respostas preenchidas continuam disponíveis para tentar de novo.

## 4. Premissas

- Depende do [Discovery 0010](0010-discovery-por-agente-em-card-aprovado.md) entregue: disparo, estados, anexo renderizado e substituição já existem
- As dúvidas são lidas da seção de dúvidas do documento gerado pela skill, que segue um formato fixo (uma dúvida por item)
- O agente roda no mesmo modo da 0010: somente leitura no repositório, com a skill de discovery, e devolve o documento completo
- Cada geração é independente no provedor de agente; o contexto (card, Discovery anterior, respostas) vai inteiro no pedido, sem depender de recurso de continuação de um provedor específico
- Depois de uma geração concluída, as respostas usadas já estão incorporadas ao novo documento e não ficam guardadas separadamente
- Gerar de novo sem respostas continua sendo a ação da 0010
- Enquanto houver geração em andamento para o card, não é possível responder nem disparar outra

## 5. Considerações de segurança

- Dados sensíveis: as respostas do PM são enviadas ao provedor de agente junto com o card e o Discovery anterior; não há dado novo além do que a 0010 já envia
- Autenticação/Autorização: responder e disparar a nova geração ficam restritos ao dono do card
- Exposição de APIs: não aplicável — não há endpoint novo exposto fora do produto
- Compliance: não aplicável além do já registrado na 0010
- Outras considerações: as respostas são texto livre que vai para o pedido do agente; não podem alterar as regras do pedido, como a proibição de editar o repositório

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

Escopo, ligação de cada resposta à sua dúvida, substituição do documento e preservação das respostas em caso de falha estão definidos; o planejamento só começa depois da entrega da 0010.
