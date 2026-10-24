<?php

namespace Tests\Unit\Support;

use App\Support\DiscoveryQuestions;
use Tests\TestCase;

class DiscoveryQuestionsTest extends TestCase
{
    public function test_section_six_list_returns_the_question_before_the_em_dash(): void
    {
        $questions = DiscoveryQuestions::fromMarkdown(<<<'MD'
## 6. Dúvidas

- Onde mostrar o aviso? — Confirmar a tela. (PM)
- A demanda cobre a API? — Impacta o contrato.

## 7. Informações ausentes

- Definição de “parecido” — não é dúvida
MD);

        $this->assertSame([
            'Onde mostrar o aviso?',
            'A demanda cobre a API?',
        ], $questions);
    }

    public function test_closed_section_returns_an_empty_list(): void
    {
        $paragraph = DiscoveryQuestions::fromMarkdown(<<<'MD'
## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

- Lacuna objetiva
MD);
        $item = DiscoveryQuestions::fromMarkdown(<<<'MD'
## 6. Dúvidas

- Nenhuma dúvida em aberto.
MD);

        $this->assertSame([], $paragraph);
        $this->assertSame([], $item);
    }

    public function test_missing_information_section_is_not_a_question(): void
    {
        $questions = DiscoveryQuestions::fromMarkdown(<<<'MD'
## 5. Considerações de segurança

- Dados sensíveis: título da tarefa — já visível no time

## 7. Informações ausentes

- Confirmação de que a demanda se aplica ao RapidTask
- Definição de negócio de “parecido”
MD);

        $this->assertSame([], $questions);
    }

    public function test_markdown_without_section_six_returns_an_empty_list(): void
    {
        $this->assertSame([], DiscoveryQuestions::fromMarkdown("# Discovery\n\nSem a seção de dúvidas."));
    }

    public function test_question_text_is_read_as_text_without_html(): void
    {
        $questions = DiscoveryQuestions::fromMarkdown(<<<'MD'
## 6. Dúvidas

- <script>alert(1)</script> qual o prazo? — importa para o aceite
MD);

        $this->assertSame(['<script>alert(1)</script> qual o prazo?'], $questions);
    }
}
