@extends('tag::layouts.master')

@section('title', 'Update Tag')

@section('content')
    {!! Form::open(['route' => ['tag.edit', $tag->id], 'method' => 'put']) !!}
    <div class="w-full container mx-auto grid">
        <div class="px-4 py-3 mb-8 bg-white rounded-lg shadow-md dark:bg-gray-800">
            @include('tag::fields')
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
@push('scripts')
    <script>
        document.getElementById('name').onkeyup = function (e) {
            copyToInput('name', 'slug', 'slug')
        }
    </script>
@endpush