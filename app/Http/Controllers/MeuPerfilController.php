<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\CadastrarFotoRequest;
use App\Http\Requests\CadastrarSenhaRequest;
use App\Models\Fechadura;
use App\Models\Admin;
use App\Services\ApiControlIdService;
use Uspdev\Wsfoto;

class MeuPerfilController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Página principal do perfil
    public function index()
    {
        $user = auth()->user();
        $fechaduras = $this->getFechadurasDoUsuario($user);

        $fechadurasStatus = $fechaduras->map(function ($fechadura) use ($user) {
            $usuarios = (new ApiControlIdService($fechadura))->loadUsers();
            $usuarioNaFechadura = collect($usuarios)->firstWhere('id', (int) $user->codpes);

            if (!$usuarioNaFechadura) return null;

            return (object) [
                'fechadura' => $fechadura,
                'tem_foto'  => $usuarioNaFechadura['image_timestamp'] > 0,
                'tem_senha' => !empty($usuarioNaFechadura['password']),
            ];
        })->filter()->values();

        return view('perfil.index', [
            'user' => $user,
            'fechadurasStatus' => $fechadurasStatus
        ]);
    }

    // Exibe a foto do usuário logado
    public function foto($id)
    {
        $user = auth()->user();
        if ($user->id != $id) abort(403);
        
        if ($user->temFotoLocal()) {
            $conteudo = Storage::disk('fotos')->get($user->foto);
            if ($conteudo) {
                return response($conteudo)->header('Content-Type', 'image/jpeg');
            }
        }

        $wsFoto = Wsfoto::obter($user->codpes);
        if ($wsFoto) {
            return response(base64_decode($wsFoto))->header('Content-Type', 'image/jpeg');
        }

        return abort(404);
    }

    public function formFotoGeral()
    {
        return view('perfil.editar_foto_geral');
    }

    // Processa o upload da foto e envia para todas as fechaduras do usuário
    public function updateFotoGeral(CadastrarFotoRequest $request)
    {
        $user = auth()->user();

        $foto = $request->foto;
        if (strpos($foto, 'base64,') !== false) {
            $foto = base64_decode(explode('base64,', $foto)[1]);
        }

        // Salva temporário para testar
        $tempName = 'temp_' . Str::uuid() . '.jpg';
        Storage::disk('fotos')->put($tempName, $foto);

        $fechaduras = $this->getFechadurasDoUsuario($user)->filter(fn($f) =>
            collect((new ApiControlIdService($f))->loadUsers())->contains('id', (int) $user->codpes)
        );

        // Testa em uma fechadura antes de salvar
        if ($fechaduras->isNotEmpty()) {
            $teste = (new ApiControlIdService($fechaduras->first()))
                ->testarFoto($fechaduras->first(), Storage::disk('fotos')->path($tempName));

            if (!$teste['success']) {
                Storage::disk('fotos')->delete($tempName);
                return back()->with('alert-danger', $teste['message']);
            }
        }

        // Aprovada — substitui foto local
        if (!is_null($user->foto)) {
            Storage::disk('fotos')->delete($user->foto);
        }

        $fotoName = Str::uuid() . '.jpg';
        Storage::disk('fotos')->put($fotoName, $foto);
        Storage::disk('fotos')->delete($tempName);

        $user->foto = $fotoName;
        $user->save();

        // Envia para todas as fechaduras
        foreach ($fechaduras as $f) {
            (new ApiControlIdService($f))->uploadFoto($user->codpes, $fotoName);
        }

        return redirect('/meu-perfil')->with('alert-success', 'Foto atualizada em todas as fechaduras!');
    }

    // Formulário para trocar senha em uma fechadura específica
    public function formSenhaFechadura(Fechadura $fechadura)
    {
        $user = auth()->user();
        $usuarios = (new ApiControlIdService($fechadura))->loadUsers();

        if (!collect($usuarios)->contains('id', (int) $user->codpes)) {
            abort(403, 'Você não está cadastrado nesta fechadura.');
        }

        return view('perfil.editar_senha_fechadura', ['fechadura' => $fechadura]);
    }

    // Processa a alteração de senha na fechadura
    public function updateSenhaFechadura(CadastrarSenhaRequest $request, Fechadura $fechadura)
    {
        $user = auth()->user();
        $success = (new ApiControlIdService($fechadura))->cadastrarSenha($user->codpes, $request->senha);

        if ($success) {
            return redirect('/meu-perfil')->with('alert-success', "Senha alterada na fechadura {$fechadura->local}.");
        }

        return back()->with('alert-danger', 'Erro ao alterar senha.');
    }

    // Retorna todas as fechaduras às quais o usuário tem acesso
    private function getFechadurasDoUsuario($user)
    {
        return Fechadura::all()->filter(function ($fechadura) use ($user) {
            try {
                $usuarios = (new ApiControlIdService($fechadura))->loadUsers();
                return collect($usuarios)->contains('id', (int) $user->codpes);
            } catch (\Exception $e) {
                return false;
            }
        })->values();
    }

}