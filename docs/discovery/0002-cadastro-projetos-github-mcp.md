# Discovery: Cadastro de projetos GitHub para uso via MCP

## 1. Resumo

O PM Helper precisa de uma forma de associar repositórios do GitHub a um nome amigável compreensível para PMs. Um administrador do sistema cadastra essa associação (nome técnico do repositório + nome exibido) em uma tabela de projetos. Essa tabela será consumida futuramente pelo MCP do GitHub para contextualizar conversas e geração de cards com dados reais do repositório. O PM nunca precisa conhecer a convenção técnica de nomes do GitHub. A autenticação com o GitHub seguirá o padrão GitHub App (mesmo modelo adotado por Cursor, Linear e similares), com um único token de instalação para todo o sistema.

## 2. Objetivo

Criar a infraestrutura de registro de projetos GitHub — tabela de projetos com nome amigável, papel de administrador no sistema e integração via GitHub App — que servirá de base para o consumo via MCP do GitHub em funcionalidades futuras.

## 3. Escopo

### Dentro

- Introdução dos papéis de usuário no sistema: `admin` e `product_manager`
- Restrição das rotas/ações de administração ao papel `admin`
- Tabela de projetos no banco de dados: nome amigável (exibido ao PM) + identificador técnico do repositório GitHub (`owner/repo`)
- Projetos são globais: visíveis e selecionáveis por todos os usuários
- Configuração da autenticação via **GitHub App** (App ID + Private Key via variáveis de ambiente; installation token único para o sistema)
- Interface de administração para cadastrar, editar e desativar projetos
- A estrutura deve seguir o padrão Ports & Adapters já estabelecido no projeto (`app/Contracts/`) para facilitar a adição de outros provedores de MCP no futuro

### Fora

- Uso efetivo do MCP nas conversas (futuro)
- Sincronização automática de dados do GitHub (issues, PRs, etc.)
- Criação ou gerenciamento de repositórios no GitHub via PM Helper
- Autenticação dos usuários PMs via GitHub (OAuth login)
- Projetos por usuário ou por time (multi-tenancy)
- Suporte a múltiplas instalações de GitHub App

## 4. Premissas

- Feature flag: **Não**
- A autenticação com o GitHub seguirá o modelo **GitHub App** — padrão de mercado usado por Cursor, Linear, etc. — com um único App instalado e um único installation token para todo o sistema
- Credenciais do GitHub App (App ID e Private Key) serão armazenadas como variáveis de ambiente, nunca no banco de dados
- "Projeto GitHub" significa um **repositório** identificado por `owner/repo` (ex: `myorg/mobile-app`)
- O padrão Ports & Adapters (`app/Contracts/LlmGateway.php`, `app/Services/LlmRouter.php`) será espelhado para MCPs — haverá um contrato de gateway para MCP
- Projetos são globais: visíveis a todos os usuários sem isolamento por tenant
- O sistema terá dois papéis: `admin` (configuração do sistema) e `product_manager` (uso do assistente)
- **Primeiro admin**: provisionado via seed do Laravel; não haverá fluxo de UI para criação do admin inicial
- **Usuário existente**: há um único usuário já cadastrado; ele será mantido e poderá ser promovido a `admin` (via seed ou Artisan command no deploy)
- **Desativação de projetos**: soft delete — registro preservado no banco, projeto fica invisível para PMs sem ser excluído fisicamente

## 5. Considerações de segurança

- **Dados sensíveis**: A Private Key do GitHub App é altamente sensível. Deve ser armazenada exclusivamente como variável de ambiente (nunca no banco). O installation token é efêmero e gerado dinamicamente — não precisa ser persistido
- **Autenticação/Autorização**: O cadastro de projetos será restrito ao papel `admin`. É necessário criar o sistema de papéis (`admin` / `product_manager`) no `User` model e proteger as rotas com middleware ou gate adequado
- **Exposição de APIs**: Novos endpoints de administração precisarão de middleware de autorização. O identification técnico do repositório (`owner/repo`) deve ser validado para evitar SSRF em chamadas futuras ao GitHub API/MCP
- **Compliance**: O GitHub App deve ser configurado com o menor escopo de permissões necessário (princípio do menor privilégio). Se o MCP tiver acesso a código-fonte, há risco de exposição de propriedade intelectual via contexto enviado ao LLM — deve ser avaliado ao implementar o MCP (fora do escopo desta entrega)
- **Outras considerações**: A rotação do installation token é gerenciada pelo GitHub (validade de 1h) — o adapter deve renovar o token automaticamente sem expô-lo

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento? Sim**

Todas as decisões de produto foram respondidas. Escopo, premissas, modelo de autenticação, papéis, visibilidade de projetos e política de desativação estão definidos e documentados.
