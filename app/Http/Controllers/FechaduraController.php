<?php

namespace App\Http\Controllers;

use App\Actions\SessionAction;
use App\Actions\LogsAction; 
use App\Actions\SyncUsersAction;
use App\Models\User;
use App\Services\FotoUpdateService;
use App\Models\Fechadura;
use App\Models\Acesso;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Uspdev\Replicado\Pessoa;
use Uspdev\Wsfoto;
use App\Services\AccessLogService;
use App\Http\Requests\FechaduraRequest;
use App\Services\ReplicadoService;
use App\Http\Controllers\UsuariosFechaduraController;

class FechaduraController extends Controller
{
    # https://www.controlid.com.br/docs/access-api-pt/primeiros-passos/realizar-login/

    # Métodos CRUD
    // Mostra fechaduras cadastradas 
    public function index() {
        $fechaduras = Fechadura::all();
        return view('fechaduras.index', [
            'fechaduras' => $fechaduras
        ]);
    }
    
    // Mostra formulário de criação
    public function create() {
        return view('fechaduras.create');
    }
    
    // Cadastra novas fechaduras
    public function store(FechaduraRequest $request) {

        $fechadura = new Fechadura();
        $fechadura->local = $request->local;
        $fechadura->ip = $request->ip;
        $fechadura->usuario = $request->usuario;
        $fechadura->senha = $request->senha;
        $fechadura->save();
    
        return redirect('/fechaduras');
    }
    
    // Mostra uma fechadura específica e lista os usuários cadastrados nela
    public function show(Fechadura $fechadura) {
        // 1 - Autenticação na API da fechadura
        $session = SessionAction::conexao($fechadura->ip, $fechadura->usuario, $fechadura->senha);
        
        // 2 - Carregamento dos usuários cadastrados na fechadura
        $route = 'http://' . $fechadura->ip . '/load_objects.fcgi?session=' . $session;
        $response = Http::post($route, [
            "object" => "users"
        ]);

        $usuarios = $response->json()['users'] ?? [];
        
        // 3 - passa os dados para a view
        return view('fechaduras.show', [
            'fechadura' => $fechadura,
            'usuarios' => $usuarios
        ]);
    }
    
    // Mostra formulário de edição
    public function edit(Fechadura $fechadura) {
        return view('fechaduras.edit', [
            'fechadura' => $fechadura
        ]);
    }
    
    // Atualiza fechadura
    public function update(FechaduraRequest $request, Fechadura $fechadura) {
        $fechadura->local = $request->local;
        $fechadura->ip = $request->ip;
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
        $fechadura->delete();
        return redirect('/fechaduras');
    }

    // Método para logs 
    public function logs(Fechadura $fechadura)
    {
        // Busca os logs do banco local, ordenados pelos mais recentes
        $acessos = Acesso::where('fechadura_id', $fechadura->id)
                    ->orderBy('datahora', 'desc')
                    ->paginate(20); // Paginação para muitos registros
                    
        return view('fechaduras.logs', [
            'fechadura' => $fechadura,
            'acessos' => $acessos
        ]);
    }

    public function updateLogs(Fechadura $fechadura)
    {
        $count = LogsAction::update($fechadura);
        
        if($count === false) {
            return back()->with('error', 'Falha ao conectar com a fechadura');
        }
        
        return back()->with('success', "{$count} logs atualizados");
    }

    //https://documenter.getpostman.com/view/7260734/S1LvX9b1?version=latest#76b4c5d7-e776-4569-bb19-341fdc1ccb7f

    public function sincronizar(Request $request, Fechadura $fechadura)
    {
        SyncUsersAction::execute($fechadura);
        return back()->with('success','Dados sincronizados');
    }
}
