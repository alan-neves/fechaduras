<?php

namespace App\Http\Controllers;

use App\Actions\CreateSetorAction;
use App\Actions\SyncUsersAction;
use App\Actions\CreateAreasAction;
use App\Services\LockSessionService;
use App\Models\Fechadura;
use Illuminate\Support\Facades\Http;
use App\Http\Requests\FechaduraRequest;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Http\Request;
use App\Services\ApiControlIdService;
use App\Http\Requests\CadastrarFotoRequest;
use App\Http\Requests\CadastrarSenhaRequest;
use App\Services\ReplicadoService;
use Illuminate\Support\Facades\Gate;
use App\Models\UsuarioExterno;
use App\Http\Requests\UsuarioExternoRequest;
use App\Services\TestarFotoService;
use App\Models\Admin;

class FechaduraController extends Controller
{
    # https://www.controlid.com.br/docs/access-api-pt/primeiros-passos/realizar-login/

    public function __construct()
    {
        $this->middleware('auth');
    }

    # Métodos CRUD
    // Mostra fechaduras cadastradas
    public function index() {
        Gate::authorize('logado');

        if (Gate::allows('admin')) {
            // Admin geral vê tudo
            $fechaduras = Fechadura::all();
        } else {
            // Usuário normal vê apenas fechaduras que administra
            $fechadurasIds = Admin::where('codpes', auth()->user()->codpes)
                                ->pluck('fechadura_id');
            $fechaduras = Fechadura::whereIn('id', $fechadurasIds)->get();
        }

        return view('fechaduras.index', [
            'fechaduras' => $fechaduras
        ]);
    }

    // Mostra formulário de criação
    public function create() {
        Gate::authorize('admin');

        return view('fechaduras.create');
    }

    // Cadastra novas fechaduras
    public function store(FechaduraRequest $request) {
        Gate::authorize('admin');

        $fechadura = new Fechadura();
        $fechadura->local = $request->local;
        $fechadura->ip = $request->ip;
        $fechadura->porta = $request->porta;
        $fechadura->usuario = $request->usuario;
        $fechadura->senha = $request->senha;
        $fechadura->save();

        return redirect('/fechaduras');
    }

    // Mostra uma fechadura específica e lista os usuários cadastrados nela
    public function show(Fechadura $fechadura) {
        Gate::authorize('adminFechadura', $fechadura);

        // 1 - Autenticação na API da fechadura
        $session = LockSessionService::conexao($fechadura->ip, $fechadura->porta, $fechadura->usuario, $fechadura->senha);

        // 2 - Carregamento dos usuários cadastrados na fechadura
        $route = 'http://' . $fechadura->ip . ':' . $fechadura->porta . '/load_objects.fcgi?session=' . $session;
        $response = Http::post($route, [
            "object" => "users"
        ]);

        $usuarios = $response->json()['users'] ?? [];
        
        // Carrega usuários externos
        $usuariosExternos = $fechadura->usuariosExternos()->with('cadastradoPor')->get();

        // Carrega administradores
        $admins = $fechadura->admins()->with('user')->get();

        // 3 - passa os dados para a view
        return view('fechaduras.show', [
            'fechadura' => $fechadura,
            'usuarios' => $usuarios,
            'usuariosExternos' => $usuariosExternos,
            'admins' => $admins,
            'programas' => ReplicadoService::programasPosUnidade()
        ]);
    }

    // Mostra formulário de edição
    public function edit(Fechadura $fechadura) {
        Gate::authorize('admin');

        return view('fechaduras.edit', [
            'fechadura' => $fechadura
        ]);
    }

    // Atualiza fechadura
    public function update(FechaduraRequest $request, Fechadura $fechadura) {
        Gate::authorize('admin');

        $fechadura->local = $request->local;
        $fechadura->ip = $request->ip;
        $fechadura->porta = $request->porta;
        $fechadura->usuario = $request->usuario;

        // Só atualiza a senha se for informada
        if($request->senha) {
            $fechadura->senha = $request->senha;
        }

        $fechadura->save();

        return redirect("/fechaduras/{$fechadura->id}");
    }

    // Deleta fechadura
    public function destroy(Fechadura $fechadura) {
        Gate::authorize('admin');

        $fechadura->delete();
        return redirect('/fechaduras');
    }

    public function createFechaduraUser(Fechadura $fechadura, Request $request){
        Gate::authorize('adminFechadura', $fechadura);

        if(!$request->codpes){
            request()->session()->flash('alert-danger', 'Informe número USP!');
            return back();
        }
        $codpes = UsuarioService::verifyAndCreateUsers($request->codpes, $fechadura);
        if (count($codpes) > 0) {
            $request->session()->flash('alert-danger', "Número(s) USP " . implode(', ', $codpes) . " não cadastrado(s).");
        }
        else {
            $request->session()->flash('alert-success', "Usuário(s) cadastrado(s) com sucesso!");
        }

        return back()->withInput();
    }

    public function createFechaduraSetor(Fechadura $fechadura, Request $request){
        Gate::authorize('adminFechadura', $fechadura);

        CreateSetorAction::execute($request->setores, $fechadura);
        request()->session()->flash('alert-success', 'Setores atualizados com sucesso!');
        return back();
    }

    public function createFechaduraPos(Request $request, Fechadura $fechadura){
        Gate::authorize('adminFechadura', $fechadura);

        CreateAreasAction::execute($request->areas, $fechadura);
        $request->session()->flash('alert-success', "Setor(es) inserido(s)");
        return back();
    }

    public function deleteUser(Fechadura $fechadura, User $user){
        Gate::authorize('adminFechadura', $fechadura);

        $fechadura->usuarios()->detach($user->id);
        request()->session()->flash('alert-warning', "{$user->name} removido");
        return back();
    }

    //https://documenter.getpostman.com/view/7260734/S1LvX9b1?version=latest#76b4c5d7-e776-4569-bb19-341fdc1ccb7f
    //Sincroniza replicado com fechadura
    public function sincronizar(Fechadura $fechadura)
    {
        Gate::authorize('adminFechadura', $fechadura);

        SyncUsersAction::execute($fechadura);
        request()->session()->flash('alert-success','Dados sincronizados!');
        return back();
    }

    //mostra view para cadastrar foto na fechadura
    public function showCadastrarFoto(Fechadura $fechadura, $userId)
    {
        Gate::authorize('adminFechadura', $fechadura);

        return view('fechaduras.cadastrar_foto', [
            'fechadura' => $fechadura,
            'userId' => $userId
        ]);
    }

    //mostra view para cadastrar senha na fechadura
    public function showCadastrarSenha(Fechadura $fechadura, $userId)
    {
        Gate::authorize('adminFechadura', $fechadura);

        return view('fechaduras.cadastrar_senha', [
            'fechadura' => $fechadura,
            'userId' => $userId
        ]);
    }

    public function cadastrarFoto(CadastrarFotoRequest $request, Fechadura $fechadura, $userId)
    {
        Gate::authorize('adminFechadura', $fechadura);

        $testarFotoService = new TestarFotoService(); 
        $result = $testarFotoService->execute($fechadura, $request->file('foto'), $userId);

        if ($result['success']) {
            return redirect("/fechaduras/{$fechadura->id}")
                ->with('alert-success', $result['message']);
        }

        return back()
            ->with('alert-danger', $result['message'])
            ->withInput();
    }

    public function cadastrarSenha(CadastrarSenhaRequest $request, Fechadura $fechadura, $userId)
    {
        Gate::authorize('adminFechadura', $fechadura);

        $apiService = new ApiControlIdService($fechadura);
        $apiService->cadastrarSenha($userId, $request->input('senha'));

        return redirect("/fechaduras/{$fechadura->id}");
    }

    // Cadastrar usuário externo
    public function createUsuarioExterno(UsuarioExternoRequest $request, Fechadura $fechadura)
    {
        Gate::authorize('adminFechadura', $fechadura);

        // Testa a foto antes de criar o usuário
        if ($request->hasFile('foto')) {
            $testarFotoService = new TestarFotoService(); 
            $resultadoTeste = $testarFotoService->execute($fechadura, $request->file('foto'));
            
            if (!$resultadoTeste['success']) {
                return back()
                    ->with('alert-danger', 'Foto não aprovada: ' . $resultadoTeste['message'])
                    ->withInput();
            }
        }

        // Cria o usuário externo
        $usuarioExterno = new UsuarioExterno();
        $usuarioExterno->nome = $request->nome;
        $usuarioExterno->fechadura_id = $fechadura->id;
        $usuarioExterno->user_id = auth()->id();
        $usuarioExterno->vinculo = $request->vinculo;
        $usuarioExterno->observacao = $request->observacao;

        // Upload da foto (já testada e aprovada)
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('usuarios_externos', 'public');
            $usuarioExterno->foto = $fotoPath;
        }

        $usuarioExterno->save();

        return back()->with('alert-success', 'Usuário externo cadastrado com sucesso!');
    }

    // Remover usuário externo
    public function deleteUsuarioExterno(Fechadura $fechadura, UsuarioExterno $usuarioExterno)
    {
        Gate::authorize('adminFechadura', $fechadura);
        $usuarioExterno->delete();
        return back()->with('alert-success', 'Usuário externo removido do sistema!');
    }

}
