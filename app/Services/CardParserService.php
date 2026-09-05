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
            'type' => $this->validateEnum($data['type'] ?? 'feature', ['feature', 'bug', 'tech_debt', 'spike']),
            'user_story' => $data['user_story'] ?? '',
            'context' => $data['context'] ?? null,
            'acceptance_criteria' => (array) ($data['acceptance_criteria'] ?? []),
            'out_of_scope' => (array) ($data['out_of_scope'] ?? []),
            'technical_notes' => $data['technical_notes'] ?? null,
            'priority' => $this->validateEnum($data['priority'] ?? 'medium', ['low', 'medium', 'high', 'critical']),
            'labels' => (array) ($data['labels'] ?? []),
            'estimated_complexity' => $this->validateEnum($data['estimated_complexity'] ?? null, ['XS', 'S', 'M', 'L', 'XL']),
        ];
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
