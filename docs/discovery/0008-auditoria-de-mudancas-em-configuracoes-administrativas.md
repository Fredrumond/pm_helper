# Discovery: Auditoria de mudanças em configurações administrativas

## 1. Resumo

Duas telas administrativas já alteram dado que afeta o sistema inteiro: a escolha do modelo ativo de cada prompt (`AdminPromptModels`, ligada ao [Discovery 0007](0007-modelo-de-cada-prompt-pelo-admin.md)) e o cadastro, edição e desativação de projeto (`AdminProjects`). Hoje cada alteração só grava uma linha em `Log::info`, junto com todo o resto do log da aplicação — sem um registro estruturado e consultável de quem fez, quando fez e o que mudou. A demanda introduz um sistema de auditoria para essas alterações administrativas, usando o pacote [Laravel Auditing](https://laravel-auditing.com/), aplicado de forma que qualquer configuração administrativa futura já entre auditada automaticamente.

## 2. Objetivo

Garantir que toda alteração feita por um admin nas configurações do sistema — hoje modelo ativo de cada prompt e cadastro/edição/desativação de projeto, e no futuro qualquer outra configuração administrativa — fique registrada de forma automática, com quem fez e quando fez, persistida no banco. Sem tela de consulta nesta entrega.

## 3. Escopo

### Dentro

- Adoção do pacote Laravel Auditing para registrar as alterações
- Aplicação da auditoria a `PromptModel` e `Project` hoje; qualquer model de configuração administrativa que entrar no futuro já nasce auditado, sem exigir uma demanda própria
- Persistência apenas no banco (tabela de audits do pacote) — sem tela ou relatório de consulta nesta entrega
- Remoção das chamadas `Log::info` equivalentes em `AdminPromptModels` e `AdminProjects`, para não duplicar o registro da mesma alteração

### Fora

- Tela ou relatório para o admin consultar o histórico (fica para entrega futura)
- Rotina de expurgo do histórico — por ora, fica retido indefinidamente
- Auditoria de ações do PM no chat (entrevista, geração de card, escolha de projeto na conversa)
- Reativação de projeto desativado — a funcionalidade não existe hoje em `AdminProjects`

## Fluxo

1. O admin altera uma configuração numa das telas administrativas (modelo de um prompt ou dado de um projeto).
2. O pacote de auditoria registra automaticamente quem fez e quando fez, sem passo extra do admin.
3. O registro fica só no banco; não há tela para consultá-lo nesta entrega.

## 4. Premissas

- A ferramenta usada é o pacote Laravel Auditing, seguindo o comportamento padrão dele para captura e persistência do registro
- O ator registrado é o usuário autenticado que executou a ação — hoje, sempre um admin, porque as duas telas já travam acesso com `ensureAdmin()`
- A auditoria cobre só escrita (criar/editar/desativar/trocar), não leitura das telas administrativas
- Vale para qualquer model de configuração administrativa, atual (`PromptModel`, `Project`) ou futuro — aplicar a ferramenta ao novo model já basta, sem tarefa extra de "adicionar auditoria"
- Sem tela de consulta nesta entrega; quem precisar consultar acessa o banco diretamente
- Sem expurgo: o histórico fica indefinidamente, por decisão do time
- As chamadas `Log::info` hoje existentes em `AdminPromptModels` e `AdminProjects` para essas mesmas ações são removidas

## 5. Considerações de segurança

- Dados sensíveis: o registro liga um usuário a uma ação administrativa (quem alterou o quê e quando). Não envolve chave de API, senha ou dado de cliente final
- Autenticação/Autorização: só quem já tem o papel admin dispara as ações auditadas, porque as telas já exigem isso hoje. Sem tela de consulta nesta entrega, o acesso ao histórico se dá por quem já acessa o banco (equipe técnica), não por uma nova camada de autorização
- Exposição de APIs: não aplicável — não há endpoint novo para fora do produto
- Compliance: manter o histórico indefinidamente liga o usuário autor a cada ação por tempo indeterminado. É um risco de retenção de dado pessoal assumido conscientemente pelo time nesta entrega, não uma lacuna
- Outras considerações: nenhuma outra identificada

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

Ferramenta, abrangência (atual e futura), ausência de tela, ausência de expurgo e remoção do log manual estão decididos.
