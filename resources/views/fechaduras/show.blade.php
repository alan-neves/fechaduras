@extends("main")
@section("content")

<div class="card">
    <div class="card-header">
        <h2>{{ $fechadura->local }}</h2>
    </div>
    
    <div class="card-body">
        <p><strong>IP:</strong> {{ $fechadura->ip }}</p>
        <p><strong>Usuário API:</strong> {{ $fechadura->usuario }}</p>
            <div class="mt-4">
                <div class="row">
                    <a href="/fechaduras" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                    <a href="/fechaduras/{{ $fechadura->id }}/edit" class="btn btn-warning">Editar</a>
                    <a href="/fechaduras/{{ $fechadura->id }}/logs" class="btn btn-info"><i class="fas fa-history"></i> Históricode acesso</a>
                    <form method="post" action="/fechaduras/{{ $fechadura->id }}/sincronizar">
                        @csrf
                        <button type="submit" class="btn btn-primary" id="btn_sync">
                            <i class="fas fa-sync-alt"></i> Sincronizar dados
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('partials.form_setor')
    @include('partials.form_posgrad')
    @include('partials.form_user')
    @include('partials.usuarios')

    <div class="card mt-4">
        <div class="card-header">
            <h3>Usuários Cadastrados ({{ count($usuarios) }})</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="thead-fflch" style="background-color: #002a5e; color: white;">
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Foto</th>
                        <th>Senha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($usuarios as $usuario)
                        <tr>
                            <td>{{ $usuario['id'] ?? 'N/A' }}</td>
                            <td>{{ $usuario['name'] ?? 'Sem nome' }}</td>
                            <td>
                                @if($usuario['image_timestamp'] > 0)
                                    <span class="text-success">✔</span>
                                    <a href="/fechaduras/{{ $fechadura->id }}/cadastrar-foto/{{ $usuario['id'] }}" 
                                    class="btn btn-sm btn-outline-secondary">
                                    Alterar foto
                                    </a>
                                @else
                                    <span class="text-danger">✖</span>
                                    <a href="/fechaduras/{{ $fechadura->id }}/cadastrar-foto/{{ $usuario['id'] }}" 
                                    class="btn btn-sm btn-outline-primary">
                                    Cadastrar foto
                                    </a>
                                @endif
                            </td>
                            <td>
                                @if(!empty($usuario['password']))
                                    <span class="text-success">✔</span>
                                    <a href="/fechaduras/{{ $fechadura->id }}/cadastrar-senha/{{ $usuario['id'] }}" 
                                    class="btn btn-sm btn-outline-secondary">
                                    Alterar senha
                                    </a>
                                @else
                                    <span class="text-danger">✖</span>
                                    <a href="/fechaduras/{{ $fechadura->id }}/cadastrar-senha/{{ $usuario['id'] }}" 
                                    class="btn btn-sm btn-outline-primary">
                                    Cadastrar senha
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">Nenhum usuário encontrado</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function(){
            $('#btn_sync').click(function(){
                let button = $(this);
                setTimeout(function(){
                    button.prop('disabled', true);
                    button.text('Sincronizando...');
                }, 5);
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection
