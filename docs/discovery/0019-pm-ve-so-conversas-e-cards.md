# Discovery: PM vê só conversas e cards

## 1. Resumo

No e2e manual de 26/10/2026 o fluxo de card e Discovery do PM passou, mas o papel ainda enxerga superfícies que a decisão do teste reservou ao admin. O PM vê no menu **Explorar** e **Documentos** (P1) e consegue usar o Explorar (P13). No card, o bloco **Consumo da sessão** aparece para o PM (P6).

A regra desta demanda: o PM opera só **Conversas** e **Cards**. Explorar, Documentos e o consumo da sessão ficam com o admin. São o mesmo ajuste de visibilidade por papel, por isso um único Discovery. Ficam de fora os itens que o próprio teste mandou rever depois (flag do Briefing, data da última sincronização, caso B5) — cada um seria outra demanda.

## 2. Objetivo

Liberar o papel de PM sem expor Explorar, Documentos nem o custo da sessão, mantendo essas superfícies disponíveis para o admin.

## 3. Escopo

### Dentro

- Menu do PM mostra só **Conversas** e **Cards**
- **Explorar** e **Documentos** somem do menu do PM
- PM não usa o Explorar: menu e acesso direto à rota ficam só para o admin (bloqueado ou redirect, no mesmo espírito das URLs admin já testadas em A12)
- **Documentos** não é liberado ao PM: some do menu e o acesso direto também fica só para o admin — o passo B8 deixa de ser fluxo de PM e passa a ser visão admin
- Admin continua vendo e usando Explorar, Documentos, projetos, métricas e versões
- No card (abertura e preview), **Consumo da sessão** aparece só para o admin; o PM vê o card sem esse bloco
- Papéis atuais se mantêm: `admin` e `product_manager`

### Fora

- Flag para ligar ou desligar o Briefing de `/docs` (P5) — rever depois; outra demanda
- Data/hora ou log da última sincronização do índice (A3) — pós-MVP; outra demanda
- Reexecutar o caso B5 (persistência do bloco `/docs` antes de gerar o card); só volta a importar com o Briefing em uso
- Validar se Explorar e entrevista “batem”, sync esquecido ou selo de card sem contexto — checklist ainda em aberto, como admin
- Editar card, exportar, desmembrar épico, comentário, notificação de Discovery concluído, dashboard por status
- Mudar o que o Explorar responde, o índice ou o conteúdo do card
- Esconder métricas, versões ou cadastro de projeto do admin

## Fluxo

1. PM entra e vê só **Conversas** e **Cards**.
2. Se tentar abrir Explorar ou Documentos pelo endereço, não entra.
3. PM segue entrevista → card → aprovar → Discovery, sem ver o consumo da sessão.
4. Admin entra, usa Explorar e Documentos, e no card vê o consumo da sessão.

## 4. Premissas

- A decisão do e2e vale “por agora”: Explorar e Documentos não são liberados ao PM; o admin segue sendo quem valida o Explorar (critério 3 do plano)
- “Consumo da sessão” é o bloco já exibido no card; a restrição vale nas telas em que o card é visto, não na tela de métricas (essa já é de admin)
- Bloqueio de URL do PM segue o comportamento já aceito em A12 (bloqueado ou redirect), sem exigir uma mensagem nova
- O isolamento entre PMs (B7) e o restante do fluxo feliz (P2–P12) permanecem como estão

## 5. Considerações de segurança

- Dados sensíveis: o Explorar e os Documentos expõem conteúdo de repositório e de arquivos `.md` já gravados; o consumo da sessão expõe tokens e custo. Não há dado novo — muda quem pode ver.
- Autenticação/Autorização: usuário continua autenticado. O PM deixa de acessar Explorar e Documentos (menu e URL). O bloco de consumo no card fica restrito ao admin. Métricas e demais URLs admin seguem como em A12.
- Exposição de APIs: não há endpoint novo; o acesso às rotas já existentes de Explorar e Documentos passa a exigir papel admin.
- Compliance: não aplicável além do controle de acesso entre os papéis já existentes.
- Outras considerações: esconder só o menu, sem barrar a URL, não cumpre “não liberar ao PM”.

## 6. Dúvidas

- Nenhuma dúvida crítica em aberto. O texto do teste separa “rotas + menu” de forma explícita só no Explorar (P13); Documentos entra pelo “não liberar ao PM” e pelo B8 virar visão admin — tratado no escopo como menu e URL.

## 7. Informações ausentes

- Nenhuma lacuna que impeça o planejamento desta regra.

## 8. Status

**Pronto para Planejamento?** Sim

P1, P13 e P6 fecham uma regra só, com comportamento esperado no e2e. P5, A3 e B5 não entram aqui.
