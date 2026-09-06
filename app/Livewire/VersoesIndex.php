<?php

namespace App\Livewire;

use App\Support\Versoes;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class VersoesIndex extends Component
{
    #[Url]
    public string $busca = '';

    #[Url]
    public string $estado = '';

    public function filtrarPor(string $estado): void
    {
        $this->estado = $this->estado === $estado ? '' : $estado;
    }

    public function limpar(): void
    {
        $this->busca = '';
        $this->estado = '';
    }

    public function render(): View
    {
        $estados = Versoes::estados();
        $estado = array_key_exists($this->estado, $estados) ? $this->estado : null;

        return view('livewire.versoes-index', [
            'estados' => $estados,
            'contagem' => Versoes::contagemPorEstado(),
            'atual' => Versoes::atual(),
            'totalReleases' => count(Versoes::todas()),
            'totalEntregas' => Versoes::totalEntregas(),
            'releases' => Versoes::filtrar($estado, $this->busca),
            'filtrando' => $estado !== null || trim($this->busca) !== '',
        ]);
    }
}
