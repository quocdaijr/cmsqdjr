@extends('administration::layouts.master')
@section('title', 'Update Permission')
@section('breadcrumbs')
    @if (Breadcrumbs::exists(Route::currentRouteName()))
        {!! Breadcrumbs::render(Route::currentRouteName(), $permission ?? null) !!}
    @endif
@endsection

@section('content')
    {!! Form::open(['route' => ['administration.permission.edit', $permission->id], 'method' => 'put']) !!}
    <div class="w-full container mx-auto grid">
        <div class="px-4 py-3 mb-8 bg-white rounded-lg shadow-md dark:bg-gray-800">
            @include('administration::permission.fields')
            <div class="mt-4 text-sm">
                <button type="submit"
                        class="w-28 px-5 py-3 font-medium font-bold leading-5 text-white transition-colors duration-150 bg-green-500 border border-transparent rounded-lg hover:bg-green-700">
                    Update
                </button>
            </div>
        </div>
    </div>
    {!! Form::close() !!}
@stop
