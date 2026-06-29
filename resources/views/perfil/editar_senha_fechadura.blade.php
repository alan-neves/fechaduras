@extends('main')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <a href="/meu-perfil" class="btn btn-secondary">Voltar</a>
                    <h4 class="mt-2">Alterar senha - {{ $fechadura->local }}</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="/meu-perfil/senha/{{ $fechadura->id }}">
                        @csrf
                        <div class="mb-3">
                            <label>Nova senha (4 dígitos)</label>
                            <input type="password" name="senha" 
                                class="form-control @error('senha') @enderror" 
                                maxlength="4">
                            @error('senha')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection