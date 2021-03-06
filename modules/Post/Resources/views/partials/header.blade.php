<x-field::text name="name" :oldValue="$post->name ?? null" :options="['id'=>'name']"/>
<x-field::text name="title" :oldValue="$post->title ?? null" :options="['id'=>'title']" />
<x-field::text name="slug" :oldValue="$post->slug ?? null" :options="['id'=>'slug']" />
<x-field::text name="description" :oldValue="$post->description ?? null" :options="['id'=>'description', 'type' => 'textarea']" />
@push('scripts')
    <script>
        document.getElementById('name').onkeyup = function (e) {
            copyToInput('name', 'slug', 'slug')
            copyToInput('name', 'title', 'text')
        }
    </script>
@endpush
