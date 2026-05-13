<?php

namespace App\Http\Controllers;

// Classes do Laravel
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
/* use Illuminate\Support\Facades\Http; */

// Classes do sistema
use App\Http\Requests\FechaduraRequest;
use App\Http\Requests\CadastrarFotoRequest;
use App\Http\Requests\CadastrarSenhaRequest;
use App\Models\Fechadura;
use App\Models\UsuarioExterno;
use App\Models\User;
use App\Models\Admin;
use App\Services\ApiControlIdService;
/* use App\Services\LockSessionService; */
use App\Services\UsuarioService;
use App\Services\ReplicadoService;

// Actions
use App\Actions\CreateSetorAction;
use App\Actions\SyncUsersAction;
use App\Actions\CreateAreasAction;
use Illuminate\Support\Str;

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

        $usuarios = (new ApiControlIdService($fechadura))->loadUsers();


        // Carrega usuários externos
        $usuariosExternos = $fechadura->usuariosExternos()->with('cadastradoPor')->get();

        // Carrega administradores
        $admins = $fechadura->admins()->with('user')->get();

        // Carrega usuarios bloqueados
        $usuariosBloqueados = $fechadura->usuariosBloqueados()->with(['bloqueadoPor','usuario'])->get();

        // 3 - passa os dados para a view
        return view('fechaduras.show', [
            'fechadura' => $fechadura,
            'usuarios' => $usuarios,
            'usuariosExternos' => $usuariosExternos,
            'admins' => $admins,
            'usuariosBloqueados' => $usuariosBloqueados,
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

    // Cadastra usuário no sistema
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

    // Cadastra setor no sistema
    public function createFechaduraSetor(Fechadura $fechadura, Request $request){
        Gate::authorize('adminFechadura', $fechadura);

        CreateSetorAction::execute($request->setores, $fechadura);
        request()->session()->flash('alert-success', 'Setores atualizados com sucesso!');
        return back();
    }

    // Cadastrar area da pós ao sistema
    public function createFechaduraPos(Request $request, Fechadura $fechadura){
        Gate::authorize('adminFechadura', $fechadura);

        CreateAreasAction::execute($request->areas, $fechadura);
        $request->session()->flash('alert-success', "Setor(es) inserido(s)");
        return back();
    }

    // Deleta usuário do sistema (não da fechadura)
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

        // Busca informações do usuário na fechadura
        $apiService = new ApiControlIdService($fechadura);
        $usuarios = $apiService->loadUsers();

        // Encontra o usuário específico
        $usuarioFechadura = collect($usuarios)->firstWhere('id', (int)$userId);

        return view('fechaduras.cadastrar_foto', [
            'fechadura' => $fechadura,
            'usuario' => $usuarioFechadura,
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

    // Cadastra foto do usuário na fechadura
    public function cadastrarFoto(CadastrarFotoRequest $request, Fechadura $fechadura, $userId)
    {
        Gate::authorize('adminFechadura', $fechadura);

        $foto = $request->safe()->foto;

        // Remove data:image/jpeg;base64, porque está passando por input type hidden
        if (strpos($foto, 'base64,') !== false) {
            $foto = base64_decode(explode('base64,', $foto)[1]);
        }

        $fotoName = Str::uuid() . '.jpg';
        Storage::disk('fotos')->put($fotoName, $foto);

        $apiService = new ApiControlIdService($fechadura);
        $result = $apiService->uploadFoto($userId, $fotoName);

        if ($result['success']) {

            $user = UsuarioExterno::find($userId - 10000) ??
                User::where('codpes', $userId)->first();

            if ( ! is_null($user->foto) ) {
                Storage::disk('fotos')->delete($user->foto);
            }
            $user->foto = $fotoName;
            $user->save();

            // Propaga para outras fechaduras (só usuários USP)
            if ($user instanceof User) {
                foreach (Fechadura::where('id', '!=', $fechadura->id)->get() as $f) {
                    $api = new ApiControlIdService($f);
                    if (collect($api->loadUsers())->contains('id', (int) $userId)) {
                        $api->uploadFoto($userId, $fotoName);
                    }
                }
            }

            return back()
                ->with('alert-success', $result['message']);

        }

        Storage::disk('fotos')->delete($fotoName);

        return back()
            ->with('alert-danger', $result['message'])
            ->withInput();
    }

    // Obtem foto cadastrada na fechadura
    public function getFoto(Fechadura $fechadura, $userId)
    {
        Gate::authorize('adminFechadura', $fechadura);

        $apiService = new ApiControlIdService($fechadura);
        $result = $apiService->getFoto($userId);

        if ($result['success']) {
            return response($result['content'])
                ->header('Content-Type', $result['content_type'])
                ->header('Cache-Control', 'no-cache');
        }

        // Placeholder (biblioteca GD)
        $img = imagecreate(200, 200);
        $bg = imagecolorallocate($img, 240, 240, 240);
        $text = imagecolorallocate($img, 180, 180, 180);
        imagestring($img, 5, 60, 90, 'Sem Foto', $text);

        ob_start();
        imagejpeg($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return response($data)->header('Content-Type', 'image/jpeg');
    }

    // Cadastra senha de usuário na fechadura
    public function cadastrarSenha(CadastrarSenhaRequest $request, Fechadura $fechadura, $userId)
    {
        Gate::authorize('adminFechadura', $fechadura);

        $apiService = new ApiControlIdService($fechadura);
        $apiService->cadastrarSenha($userId, $request->input('senha'));

        return redirect("/fechaduras/{$fechadura->id}");
    }

    // Exclui usuario da fechadura
    public function excluirUsuarioFechadura(Fechadura $fechadura, $userId)
    {
        Gate::authorize('adminFechadura', $fechadura);

        $apiService = new ApiControlIdService($fechadura);
        $resultado = $apiService->deleteUser($userId);

        if ($resultado['success']) {

            UsuarioService::delete($fechadura, $userId);

            return back()->with('alert-success', 'Usuário excluído da fechadura!');
        }

        return back()->with('alert-danger', 'Erro ao excluir usuário: ' . ($resultado['error'] ?? 'Erro desconhecido'));
    }
}
