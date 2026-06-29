<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\CadastrarFotoRequest;
use App\Http\Requests\CadastrarSenhaRequest;
use App\Models\Fechadura;
use App\Services\ApiControlIdService;
use App\Actions\ObterFotoLocalRemota;
use App\Actions\ObterFechadurasUsuario;
use Illuminate\Support\Facades\Auth;

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

        $fechadurasStatus = Fechadura::all()->map(function ($fechadura) use ($user) {
            try {
                $usuarioNaFechadura = (new ApiControlIdService($fechadura))->loadUser($user->codpes);
            } catch (\Exception) {
                return null;
            }

            if (!$usuarioNaFechadura) return null;

            return (object) [
                'fechadura' => $fechadura,
                'tem_foto'  => $usuarioNaFechadura['image_timestamp'] > 0,
                'tem_senha' => !empty($usuarioNaFechadura['password']),
            ];

        })->filter()->values();

        $foto = ObterFotoLocalRemota::handle(Auth::user());

        return view('perfil.index', [
            'user' => $user,
            'fechadurasStatus' => $fechadurasStatus,
            'foto' => $foto
        ]);
    }

    public function FotoGeral()
    {
        $foto = ObterFotoLocalRemota::handle(Auth::user());

        return view('perfil.editar_foto_geral', [
            'foto' => $foto
        ]);
    }

    // Processa o upload da foto e envia para todas as fechaduras do usuário
    public function updateFotoGeral(CadastrarFotoRequest $request)
    {
        $user = auth()->user();

        $fechaduras = ObterFechadurasUsuario::handle($user);

        // Testa em uma fechadura antes de salvar
        if ($fechaduras->isNotEmpty()) {

            $foto = $request->safe()->string('foto');
            if (strpos($foto, 'base64,') !== false) {
                $foto = base64_decode(explode('base64,', $foto)[1]);
            }

            $fotoName = Str::uuid() . '.jpg';
            Storage::disk('fotos')->put($fotoName, $foto);

            $teste = (new ApiControlIdService($fechaduras->first()))
                ->testarFoto($fechaduras->first(), Storage::disk('fotos')->path($fotoName));

            if (!$teste['success']) {
                Storage::disk('fotos')->delete($fotoName);
                return back()->with('alert-danger', $teste['message']);
            }

            if (!is_null($user->foto)) {
                Storage::disk('fotos')->delete($user->foto);
            }

            $user->foto = $fotoName;
            $user->save();

            // Envia para todas as fechaduras
            foreach ($fechaduras as $fechadura) {
                (new ApiControlIdService($fechadura))->uploadFoto($user->codpes, $fotoName);
            }

            return redirect('/meu-perfil')->with('alert-success', 'Foto atualizada em todas as fechaduras!');
        }

        return back()->with('alert-info', 'O usuário não possui cadastro em nenhuma fechadura!');

    }

    // Formulário para trocar senha em uma fechadura específica
    public function formSenhaFechadura(Fechadura $fechadura)
    {
        $user = auth()->user();
        if (!(new ApiControlIdService($fechadura))->loadUser($user->codpes)) {
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

}
