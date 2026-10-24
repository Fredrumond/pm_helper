---
name: discovery
description: >-
  Realiza o Discovery inicial de uma demanda de software antes do planejamento
  técnico e grava o documento em docs/discovery/ na hora. O chat fica para
  correções. Inclui fluxo simples quando a demanda exigir, título pela essência
  e considerações de segurança quando a informação não estiver na entrada.
  Use quando o usuário pedir discovery, levantamento inicial, escopo de demanda,
  refinamento de requisito, ou precisar estruturar uma demanda antes do
  planejamento técnico.
disable-model-invocation: true
---

# Skill: Discovery

## Objetivo

Transformar uma demanda em um documento de Discovery estruturado e gravá-lo em arquivo.

---

## Decisões de design

### Arquivo é o artefato; chat é correção

| Ação | Quando | Onde |
|------|--------|------|
| **Gravar o documento** | Assim que houver contexto suficiente para as seções obrigatórias | `docs/discovery/NNNN-titulo.md` |
| **Responder no chat** | Sempre, em poucas linhas | Caminho do arquivo, status e convite a correção |
| **Corrigir** | Usuário pede ajuste na mesma demanda | O mesmo arquivo |
| **Versionar no Git** | Decisão do time no momento do commit | Fora do escopo desta skill |

**Por quê:**

- O Markdown estruturado é o núcleo da skill — força clareza de escopo, premissas e lacunas.
- Colar o documento (ou um resumo dele) no chat duplica o artefato e deixa a conversa como cópia desatualizada.
- O arquivo em `docs/discovery/` é a referência da demanda, inclusive enquanto ainda há lacunas. O status dentro do arquivo diz se já dá para planejar.
- Correção da mesma demanda atualiza o arquivo existente. Um número novo só nasce para outra demanda.
- Versionamento no Git não é responsabilidade da skill; o time commita quando fizer sentido.

### Contexto insuficiente

- **Não** gere o documento completo.
- **Não** crie arquivo em `docs/discovery/`.
- Liste exatamente quais informações precisam ser obtidas antes de estruturar o Discovery.

### Feature flag fora do Discovery

Feature flag é decisão de estratégia de entrega, particular demais para um gate de toda demanda. A skill não pergunta e não exige o campo. Se a entrada já trouxer esse fato, registre como premissa comum.

### Fluxo só quando esclarece

Desenhe um fluxo quando a demanda depender de sequência, estados ou jornada que o texto sozinho não deixa óbvia. Demanda sem esse eixo não ganha diagrama. O fluxo descreve a regra de negócio ou a jornada, em poucos passos. Arquitetura, componentes e infraestrutura continuam fora desta skill.

### Título pela essência

O título — e o slug do arquivo — nomeiam o que a demanda trata: a mudança ou o resultado, em poucas palavras, específico o bastante para alguém entender o assunto só pelo título. Evite rótulo genérico, só o id do card, ou a cópia do título longo da issue.

---

## Responsabilidades

- Entender a demanda.
- Resumir a regra de negócio no documento.
- Identificar o objetivo.
- Definir escopo.
- Identificar premissas.
- Desenhar um fluxo simples e objetivo quando a demanda precisar.
- Identificar e documentar considerações de segurança.
- Dar ao documento um título com a essência da demanda.
- Levantar dúvidas.
- Identificar informações ausentes.
- Gravar o documento em arquivo assim que ele existir.
- Aplicar correções no mesmo arquivo.

---

## Restrições

Nunca:

- sugerir arquitetura;
- gerar plano técnico;
- escrever código;
- criar ADR;
- estimar esforço;
- commitar ou versionar arquivos automaticamente;
- colar no chat o documento, um resumo da demanda ou as seções preenchidas;
- inventar ou omitir riscos de segurança quando a informação não estiver na entrada;
- desenhar fluxo de arquitetura, componentes, serviços ou infraestrutura;
- criar um arquivo novo para corrigir a mesma demanda.

Caso não exista contexto suficiente, interrompa o processo e liste exatamente quais informações precisam ser obtidas antes de estruturar o Discovery.

---

## Fluxo

### Etapa 1 — Compreender a demanda

Leia o contexto disponível (mensagem do usuário, issues, documentos, conversas anteriores).

Se o contexto for insuficiente para preencher as seções obrigatórias com segurança, **pare aqui**. Não gere o documento. Liste exatamente quais informações precisam ser obtidas.

### Etapa 2 — Identificar considerações de segurança (gate)

**Antes** de estruturar o Discovery, avalie se há aspectos de segurança relevantes:

- Dados sensíveis (PII, credenciais, tokens)?
- Necessidade de autenticação/autorização?
- Exposição de APIs ou endpoints novos?
- Alteração em controles de acesso existentes?
- Requisitos de compliance (LGPD, PCI, etc.)?

Se houver algum aspecto relevante, documente na seção **Considerações de segurança**.

Se não houver aspectos de segurança significativos, registre explicitamente: `Não identificadas considerações de segurança relevantes para esta demanda.`

Não invente riscos. Se houver dúvida, pergunte ao usuário.

### Etapa 3 — Definir o título

Escreva o título pela essência da demanda, em português.

- Diz o que muda ou o que se entrega.
- Cabe numa linha e se sustenta sozinho.
- O slug do arquivo repete essa essência em kebab-case.

Exemplos de essência: `Aviso por e-mail antes do vencimento da assinatura`, `Cancelamento de pedido pelo cliente`.

### Etapa 4 — Estruturar e gravar

Com contexto suficiente (incluindo as considerações de segurança), preencha a saída obrigatória e **grave o arquivo na hora**.

Regras durante o preenchimento:

- Separe fato de suposição — premissas devem ser explícitas.
- Escopo deve deixar claro o que está **dentro** e o que está **fora**.
- Se a entrada já disser que haverá feature flag, registre isso como premissa comum. Não pergunte e não crie campo obrigatório.
- Inclua a seção **Fluxo** somente quando a demanda depender de sequência, estados ou jornada. Fluxo simples: diagrama mermaid curto ou lista numerada, em linguagem de negócio, com poucos passos. Omita a seção quando não houver esse eixo.
- Em **Considerações de segurança**, registre fatos e lacunas; não invente riscos.
- Dúvidas devem ser acionáveis (quem responde, o que precisa ser decidido).
- Informações ausentes são lacunas objetivas, não opiniões.
- Defina o status ao preencher a seção Status:
  - **Pronto para Planejamento** — escopo e objetivo claros; dúvidas críticas respondidas ou aceitas como risco explícito.
  - **Bloqueado** — lacunas impedem planejamento seguro; liste o que falta obter na seção Informações ausentes.
- Grave também quando o status for **Pronto para Planejamento? Não**. As lacunas ficam no arquivo.

Caminho:

```
docs/discovery/NNNN-titulo.md
```

Convenções:

- `NNNN`: número sequencial com 4 dígitos (`0001`, `0002`, …).
- `titulo`: slug em kebab-case, em português, com a mesma essência do título do documento.
- Antes de criar, liste `docs/discovery/` e use o próximo número disponível.
- Exemplo: título `Aviso por e-mail antes do vencimento da assinatura` → `docs/discovery/0001-aviso-por-email-antes-do-vencimento.md`.
- Não sobrescreva o arquivo de outra demanda.

### Etapa 5 — Responder no chat

Depois de gravar, a resposta contém só:

- o caminho do arquivo;
- **Pronto para Planejamento?** Sim ou Não, com a mesma justificativa de uma frase da seção Status;
- o convite para corrigir no chat.

Não repita resumo, objetivo, escopo, fluxo nem o Markdown do documento.

### Etapa 6 — Corrigir

Quando o usuário corrigir, complementar ou responder uma dúvida da mesma demanda:

- edite o arquivo já criado;
- atualize status, dúvidas e lacunas conforme o que foi resolvido;
- no chat, diga o que mudou em uma ou duas frases e repita o caminho do arquivo.

Não crie outro número. Não cole o documento de volta no chat.

---

## Saída obrigatória

Gere um documento Markdown contendo estas seções, nesta ordem. A seção **Fluxo** entra somente quando a Etapa 4 pedir.

```markdown
# Discovery: [Essência da demanda]

## 1. Resumo

[Parágrafo curto com a regra de negócio e o contexto da demanda.]

## 2. Objetivo

[O que se pretende alcançar com esta entrega.]

## 3. Escopo

### Dentro

- [Item]

### Fora

- [Item]

## Fluxo

[Somente se a demanda depender de sequência, estados ou jornada.
Mermaid curto ou lista numerada, em linguagem de negócio.
Omita este título quando não se aplicar.]

## 4. Premissas

- [Premissa assumida]

## 5. Considerações de segurança

- Dados sensíveis: [Quais dados / Não aplicável]
- Autenticação/Autorização: [Requisitos / Não aplicável]
- Exposição de APIs: [Endpoints novos ou alterados / Não aplicável]
- Compliance: [LGPD, PCI, outros / Não aplicável]
- Outras considerações: [Descrição / Nenhuma identificada]

[Se não houver aspectos relevantes: "Não identificadas considerações de segurança relevantes para esta demanda."]

## 6. Dúvidas

- [Dúvida] — [por que importa / quem pode responder]

## 7. Informações ausentes

- [Lacuna objetiva que ainda precisa ser levantada]

## 8. Status

**Pronto para Planejamento?** [Sim / Não]

[Justificativa em uma ou duas frases.]
```

Grave este conteúdo em `docs/discovery/`. No chat, siga a Etapa 5.

---

## Regras

- Seja objetivo; evite repetir texto integral de issues ou conversas.
- Não avance para planejamento técnico — este documento encerra na etapa de Discovery.
- Faça perguntas quando faltarem informações, em vez de inventar contexto.
- Não invente nem omita riscos de segurança: se houver dúvida, use a Etapa 2.
- O título e o slug carregam a essência da demanda.
- Fluxo é simples, objetivo e só aparece quando esclarece a demanda.
- O documento vive no arquivo. O chat confirma, aponta o status e recebe correções.
