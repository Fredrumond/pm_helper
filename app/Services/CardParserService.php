<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Collection;

class CardParserService
{
    /**
     * Verifica se a resposta do assistente contém um card gerado.
     */
    public function hasCard(string $content): bool
    {
        return str_contains($content, '<CARD_JSON>') && str_contains($content, '</CARD_JSON>');
    }

    /**
     * Verifica se a entrevista foi sinalizada como completa.
     */
    public function hasInterviewComplete(string $content): bool
    {
        return $this->extractInterviewSummary($content) !== null
            || $this->looksInterviewReady($content);
    }

    /**
     * Detecta fechamento da entrevista em linguagem natural, sem as tags.
     */
    public function looksInterviewReady(string $content): bool
    {
        $normalized = mb_strtolower($content);

        return (bool) preg_match(
            '/entrevista (está|esta) (fechada|pronta|completa|finalizada)|entrevista finalizada|já pode gerar o card|pode gerar o card no produto|botão.{0,40}gerar card|gerar card.{0,60}(aparecer|produto)|resumo do entendimento|tenho tudo que preciso/u',
            $normalized
        );
    }

    /**
     * Detecta pedido explícito do PM para gerar o card.
     */
    public function isGenerateCardRequest(string $content): bool
    {
        $normalized = mb_strtolower(trim($content));

        return (bool) preg_match(
            '/^(gere|gerar|cria|criar)(\s+o)?\s+card\b/u',
            $normalized
        );
    }

    /**
     * Extrai o resumo estruturado da entrevista.
     */
    public function extractInterviewSummary(string $content): ?string
    {
        if (preg_match('/<INTERVIEW_SUMMARY>(.*?)<\/INTERVIEW_SUMMARY>/is', $content, $matches) === 1) {
            $summary = trim($matches[1]);

            return $summary !== '' ? $summary : null;
        }

        return null;
    }

    /**
     * Monta um resumo a partir do histórico quando o modelo não emitiu as tags.
     *
     * @param  Collection<int, Message>|iterable<int, Message>  $messages
     */
    public function summaryFromMessages(iterable $messages): ?string
    {
        $parts = [];

        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                continue;
            }

            $text = $this->extractTextOnly($message->content);

            if ($text === '' || str_starts_with($text, '⚠️')) {
                continue;
            }

            $label = $message->isFromUser() ? 'PM' : 'Assistente';
            $parts[] = $label.': '.$text;
        }

        if ($parts === []) {
            return null;
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * Extrai e valida o JSON do card a partir da resposta do assistente.
     *
     * @throws \InvalidArgumentException
     */
    public function parse(string $content): array
    {
        preg_match('/<CARD_JSON>(.*?)<\/CARD_JSON>/s', $content, $matches);

        if (empty($matches[1])) {
            throw new \InvalidArgumentException('Bloco <CARD_JSON> não encontrado na resposta.');
        }

        $json = trim($matches[1]);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('JSON inválido no bloco CARD_JSON: '.json_last_error_msg());
        }

        return $this->normalize($data);
    }

    /**
     * Normaliza e garante os campos obrigatórios do card.
     */
    private function normalize(array $data): array
    {
        return [
            'title' => $data['title'] ?? 'Card sem título',
            'objetivo' => $this->nullableString($data['objetivo'] ?? null) ?? '',
            'como_funciona_hoje' => $this->nullableString($data['como_funciona_hoje'] ?? null),
            'regras' => $this->stringList($data['regras'] ?? []),
            'onde' => $this->stringList($data['onde'] ?? []),
            'aceite' => $this->stringList($data['aceite'] ?? []),
            'o_que_nao_fazer' => $this->stringList($data['o_que_nao_fazer'] ?? []),
            'stakeholders' => $this->stringList($data['stakeholders'] ?? []),
            'como_validar' => $this->nullableString($data['como_validar'] ?? null),
            'priority' => $this->validateEnum($data['priority'] ?? 'medium', ['low', 'medium', 'high', 'critical']),
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            array_map(function (mixed $item): string {
                if (is_string($item)) {
                    return trim($item);
                }

                if (is_scalar($item)) {
                    return trim((string) $item);
                }

                return '';
            }, (array) $value),
            fn (string $item): bool => $item !== '',
        ));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function validateEnum(?string $value, array $allowed): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    /**
     * Remove as tags de sinalização da resposta para exibição do texto limpo.
     */
    public function extractTextOnly(string $content): string
    {
        $content = preg_replace('/<CARD_JSON>.*?<\/CARD_JSON>/s', '', $content) ?? $content;
        $content = preg_replace('/<INTERVIEW_COMPLETE>.*?<\/INTERVIEW_COMPLETE>/is', '', $content) ?? $content;
        $content = preg_replace('/<INTERVIEW_SUMMARY>.*?<\/INTERVIEW_SUMMARY>/is', '', $content) ?? $content;

        return trim($content);
    }
}
