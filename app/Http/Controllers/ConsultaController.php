<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Services\ConsultaIp;
use App\Services\Relatorio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ConsultaController extends Controller
{
    // Fusos que aparecem em ofício: Brasília, UTC/GMT e os demais do Brasil.
    private const FUSOS = ['America/Sao_Paulo', 'UTC', 'America/Manaus', 'America/Rio_Branco', 'America/Noronha'];

    public function __construct(private readonly ConsultaIp $consultaIp) {}

    public function index(): JsonResponse
    {
        return response()->json(Consulta::with('usuario:id,name')->latest()->limit(200)->get()
            ->map(fn (Consulta $c) => $c->only('id', 'autoridade', 'documento', 'ip', 'porta', 'protocolo', 'momento', 'conclusao', 'created_at', 'relatorio_sha256') + ['usuario' => $c->usuario?->name]));
    }

    /** Pré-visualização: roda a consulta sem registrar nada. */
    public function buscar(Request $request): JsonResponse
    {
        [$dados, $momento] = $this->validar($request);
        return response()->json($this->consultaIp->buscar($dados['ip'], $dados['porta'] ?? null, $momento, $dados['protocolo'] ?? null));
    }

    /** Registra a consulta (refaz a busca no servidor) e gera o relatório. */
    public function store(Request $request, Relatorio $relatorio): JsonResponse
    {
        [$dados, $momento] = $this->validar($request, true);
        $resultado = $this->consultaIp->buscar($dados['ip'], $dados['porta'] ?? null, $momento, $dados['protocolo'] ?? null);

        $consulta = Consulta::create([
            'autoridade' => $dados['autoridade'] ?? null, 'documento' => $dados['documento'] ?? null,
            'processo' => $dados['processo'] ?? null, 'recebido_em' => $dados['recebido_em'] ?? null,
            'ip' => $dados['ip'], 'porta' => $dados['porta'] ?? null, 'protocolo' => $dados['protocolo'] ?? null,
            'momento' => $momento, 'momento_informado' => "{$dados['data']} {$dados['hora']}", 'fuso_informado' => $dados['fuso'],
            'resultado' => $resultado, 'conclusao' => $resultado['conclusao'], 'user_id' => $request->user()->id,
        ]);
        $relatorio->gerar($consulta->load('usuario'));

        return response()->json($consulta->fresh(), 201);
    }

    public function show(Consulta $consulta): JsonResponse
    {
        return response()->json($consulta->load('usuario:id,name'));
    }

    public function pdf(Consulta $consulta)
    {
        abort_unless($consulta->relatorio_arquivo && Storage::disk('local')->exists($consulta->relatorio_arquivo), 404);
        return Storage::disk('local')->download($consulta->relatorio_arquivo);
    }

    /** @return array{0: array, 1: Carbon} */
    private function validar(Request $request, bool $completo = false): array
    {
        $dados = $request->validate([
            'ip' => ['required', 'ip'],
            'porta' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'protocolo' => ['nullable', 'in:tcp,udp'],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'fuso' => ['required', 'in:' . implode(',', self::FUSOS)],
        ] + ($completo ? [
            'autoridade' => ['nullable', 'string', 'max:200'],
            'documento' => ['nullable', 'string', 'max:120'],
            'processo' => ['nullable', 'string', 'max:120'],
            'recebido_em' => ['nullable', 'date_format:Y-m-d'],
        ] : []));

        $momento = Carbon::parse("{$dados['data']} {$dados['hora']}", $dados['fuso'])->setTimezone(config('app.timezone'));
        return [$dados, $momento];
    }
}
