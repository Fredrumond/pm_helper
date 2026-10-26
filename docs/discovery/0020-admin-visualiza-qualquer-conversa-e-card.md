# Discovery: Admin visualiza qualquer conversa e card

## 1. Resumo

Hoje cada usuário só abre as próprias conversas e os próprios cards (mesmo o admin). O e2e de 26/10/2026 deixou em aberto se o admin deveria ver cards e Discovery de outros. A regra desta demanda: o admin visualiza qualquer conversa e qualquer card, de qualquer usuário. Ele não opera o fluxo desses itens — não interage; só lê. O PM continua vendo e operando só o que é seu.

## 2. Objetivo

Permitir que o admin acompanhe o trabalho dos PMs (entrevista, card e Discovery já gerado) sem assumir o papel de dono nem alterar esses registros.

## 3. Escopo

### Dentro

- Admin abre qualquer conversa de entrevista e lê o histórico
- Admin abre qualquer card e lê o conteúdo, inclusive Discovery já gerado
- Admin encontra esses itens nas listas de Conversas e Cards (não só pelo endereço)
- Em item de outro usuário, o admin não interage: não envia mensagem, não escolhe projeto, não gera nem aprova card, não dispara nem regenera Discovery, não responde dúvidas da seção 6, não exclui a conversa
- PM segue isolado dos itens de outro PM (B7)

### Fora

- Menu, rotas e consumo da sessão da demanda 0019 (PM vê só Conversas e Cards)
- Admin operar as próprias conversas e cards — isso permanece como está
- Explorar (já é superfície de admin; isolamento entre explorações de admins diferentes não entra aqui)
- Documentos, projetos, métricas, versões, catálogo LLM
- Editar card, exportar, desmembrar épico, comentário, notificação, dashboard por status
- Filtro avançado por PM, busca ou tela nova de auditoria

## Fluxo

1. Admin entra em Conversas ou Cards e vê os itens de todos os usuários.
2. Abre a conversa ou o card de um PM.
3. Lê histórico, card e Discovery já gerado, se houver.
4. Não consegue alterar o estado daquele item.
5. O PM dono continua operando o fluxo normalmente; outro PM continua sem acesso (B7).

## 4. Premissas

- Papéis atuais se mantêm: `admin` e `product_manager`
- “Não interagir” vale sobre item cujo dono não é o admin. O admin continua podendo usar Conversas e Cards nos itens que são dele
- Visualizar inclui lista e abertura; sem a lista, “ver tudo” não fecha
- O isolamento entre PMs (B7) não muda
- No card que o admin abrir, o bloco Consumo da sessão segue a regra da 0019 (visível para admin)

## 5. Considerações de segurança

- Dados sensíveis: o admin passa a ler entrevista, card, Discovery e, no card, tokens e custo da sessão de qualquer usuário. Não há tipo de dado novo — amplia quem pode ver o que já existe
- Autenticação/Autorização: usuário continua autenticado. O admin ganha leitura cruzada. O PM não ganha acesso ao item de outro PM. Ações que mudam estado no item de outro usuário permanecem recusadas
- Exposição de APIs: não há endpoint novo; as rotas já existentes de conversa e card passam a aceitar leitura do admin. Esconder só o botão, sem recusar a ação, não cumpre “não interagir”
- Compliance: não aplicável além do controle de acesso entre os papéis já existentes
- Outras considerações: o conteúdo das entrevistas pode incluir contexto interno do produto; a leitura do admin é intencional e restrita a esse papel

## 6. Dúvidas

- A lista do admin precisa mostrar de quem é cada conversa/card, ou basta misturar os itens? — define se a lista fica usável; quem decide: time
- Vale o mesmo para explorações de outro admin, ou Explorar fica de fora mesmo? — o texto falou em conversa e card; Explorar é outra superfície; quem decide: time

## 7. Informações ausentes

- Nenhuma lacuna que impeça o planejamento desta regra, se as premissas acima forem aceitas.

## 8. Status

**Pronto para Planejamento?** Sim

A regra de leitura do admin e de não operar item de outro usuário está clara, com o isolamento entre PMs intacto. As duas dúvidas são de detalhe de lista e de Explorar, não bloqueiam o núcleo.
