# RAG — Documentos como Contexto

> Retrieval-Augmented Generation: o usuário faz upload de arquivos `.md`, o sistema indexa o conteúdo com embeddings e usa os trechos mais relevantes como contexto para a LLM responder perguntas.

---

## Componentes

| Camada | Classe |
|---|---|
| Upload e orquestração | `Livewire\Documents` |
| Chunking + geração de embedding | `Services\DocumentChunker` |
| Geração de embedding (OpenAI) | `Services\EmbeddingService` |
| Busca por similaridade | `Services\DocumentSearch` |
| Cálculo de cosseno | `Services\CosineSimilarity` |
| Resposta com contexto (RAG) | `Services\DocumentRag` ← **somente no Explore** |
| Integração no chat Explore | `Livewire\ExploreChat` |
| Prompt do RAG | `resources/prompts/rag/v1.md` |

> **Separação de responsabilidades:** a tela **Documentos** só busca e exibe os chunks mais similares (sem LLM). A LLM é chamada exclusivamente pelo **ExploreChat**, que usa o `DocumentRag` para gerar uma resposta contextualizada.

**Banco de dados**

| Tabela | Propósito |
|---|---|
| `documents` | Nome e conteúdo bruto do arquivo |
| `document_chunks` | Trechos do tamanho configurado no painel + vetor de embedding (JSON) |

---

## Fluxo 1 — Ingestão de documento

```mermaid
sequenceDiagram
    actor U as Usuário
    participant D as Documents (Livewire)
    participant Ch as DocumentChunker
    participant E as EmbeddingService
    participant DB as Banco de dados

    U->>D: Faz upload de arquivo .md (≤ 5 MB)
    D->>D: Valida extensão e tamanho
    D->>DB: Salva Document (name, content)
    D->>Ch: chunk(document)
    Ch->>DB: Exclui chunks anteriores
    loop Cada bloco do tamanho configurado
        Ch->>E: embed(trecho)
        E->>E: POST /v1/embeddings (modelo de embedding do painel)
        E-->>Ch: vetor float[]
        Ch->>DB: Salva DocumentChunk (content, chunk_index, embedding)
    end
    D-->>U: "Documento enviado."
```

---

## Fluxo 2 — Busca de chunks (tela Documentos)

> A tela Documentos **não chama a LLM**. Ela apenas recupera e exibe os chunks mais próximos por similaridade de cosseno — útil para inspecionar o índice e validar a qualidade dos embeddings.

```mermaid
sequenceDiagram
    actor U as Usuário
    participant D as Documents (Livewire)
    participant S as DocumentSearch
    participant E as EmbeddingService
    participant C as CosineSimilarity
    participant DB as Banco de dados

    U->>D: Digita pergunta e clica Buscar
    D->>S: search(question)
    S->>E: embed(question)
    E->>E: POST /v1/embeddings (modelo de embedding do painel)
    E-->>S: vetor da pergunta
    S->>DB: Carrega todos os DocumentChunks com embedding
    loop Cada chunk
        S->>C: score(query_vec, chunk_vec)
        C-->>S: similaridade cosseno (-1 a 1)
    end
    S-->>D: top-K chunks mais similares
    D-->>U: Exibe chunks com score e conteúdo (sem resposta LLM)
```

---

## Fluxo 3 — RAG com resposta LLM (ExploreChat)

> O **ExploreChat** usa o `DocumentRag` para gerar uma resposta textual usando os chunks como contexto. A LLM **só é ativada aqui**.

```mermaid
sequenceDiagram
    actor U as Usuário
    participant E as ExploreChat (Livewire)
    participant R as DocumentRag
    participant S as DocumentSearch
    participant L as LlmGateway

    U->>E: Envia mensagem no Explore
    E->>R: ask(question, gpt-4o-mini)
    R->>S: search(question)
    S-->>R: top-K chunks mais similares
    R->>L: completePrompt([contexto + pergunta], "rag")
    L-->>R: resposta textual
    R-->>E: { matches[], answer }
    E-->>U: Exibe resposta gerada pela LLM
```

## Fluxo 4 — Prioridade RAG vs. Explore padrão

```mermaid
flowchart TD
    A[Usuário envia mensagem no Explore] --> B{Existem chunks\ncom embedding?}
    B -- Não --> C[answerFromExplore\nLLM com system prompt explore/v1]
    B -- Sim --> D[answerFromDocuments\nDocumentRag.ask com gpt-4o-mini]
    D --> E{Resposta não\nnula?}
    E -- Sim --> F[Retorna resposta baseada\nnos documentos]
    E -- Não --> C
    C --> G[Salva mensagem assistant\nno banco]
    F --> G
```

> **Prioridade:** se há documentos indexados, o Explore **sempre** tenta responder via RAG antes de usar o flow padrão de explore.

---

## Modelo de dados

```mermaid
erDiagram
    documents {
        bigint id PK
        string name
        longtext content
        timestamp created_at
        timestamp updated_at
    }
    document_chunks {
        bigint id PK
        bigint document_id FK
        longtext content
        int chunk_index
        json embedding
        timestamp created_at
        timestamp updated_at
    }
    documents ||--o{ document_chunks : "tem"
```

---

## Parâmetros relevantes

| Parâmetro | Valor | Onde |
|---|---|---|
| Tamanho do chunk | o definido no painel; até a primeira troca, 500 caracteres | `RagConfig` |
| Modelo de embedding | o definido no painel, escolhido no catálogo; até a primeira troca, `text-embedding-3-small` | `RagConfig` |
| Top-K resultados | o definido no painel; até a primeira troca, 3 | `RagConfig` |
| Modelo de resposta no Explore | `gpt-4o-mini` | `ExploreChat::MODEL` |
| Versão do prompt RAG | `v1` | `config/chat.php` → `prompts.rag` |

---

## Limitações atuais

- **Sem vector store:** a busca por similaridade carrega **todos** os chunks em memória — OK para volumes pequenos, inviável com muitos documentos.
- **Chunk por caractere:** o split no tamanho configurado pode cortar palavras no meio; chunking semântico (por parágrafo/sentença) daria contexto mais preciso.
- **Trocar tamanho ou modelo de embedding não reindexa:** os trechos já gravados permanecem até o documento ser embeddado de novo. O painel avisa que a reindexação ficou pendente. O top-K vale na busca seguinte.
- **Embedding requer `OPENAI_API_KEY`:** sem a chave, o upload falha no momento do chunking.
- **Sem score mínimo:** qualquer chunk é retornado mesmo com similaridade baixa.
