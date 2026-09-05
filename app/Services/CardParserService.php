<?php

namespace App\Services;

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
            throw new \InvalidArgumentException('JSON inválido no bloco CARD_JSON: ' . json_last_error_msg());
        }

        return $this->normalize($data);
    }

    /**
     * Normaliza e garante os campos obrigatórios do card.
     */
    private function normalize(array $data): array
    {
        return [
            'title'                => $data['title'] ?? 'Card sem título',
            'type'                 => $this->validateEnum($data['type'] ?? 'feature', ['feature', 'bug', 'tech_debt', 'spike']),
            'user_story'           => $data['user_story'] ?? '',
            'context'              => $data['context'] ?? null,
            'acceptance_criteria'  => (array) ($data['acceptance_criteria'] ?? []),
            'out_of_scope'         => (array) ($data['out_of_scope'] ?? []),
            'technical_notes'      => $data['technical_notes'] ?? null,
            'priority'             => $this->validateEnum($data['priority'] ?? 'medium', ['low', 'medium', 'high', 'critical']),
            'labels'               => (array) ($data['labels'] ?? []),
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
     * Remove as tags CARD_JSON da resposta para exibição do texto limpo.
     */
    public function extractTextOnly(string $content): string
    {
        return trim(preg_replace('/<CARD_JSON>.*?<\/CARD_JSON>/s', '', $content));
    }
}
