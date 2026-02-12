<?php

namespace Modules\Tag\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\CoreController;
use Modules\Tag\Http\Requests\CreateTagRequest;
use Modules\Tag\Http\Requests\UpdateTagRequest;
use Modules\Tag\Jobs\IndexTagElasticsearch;
use Modules\Tag\Repositories\Interfaces\TagRepositoryInterface;

class TagController extends CoreController
{
    public function __construct(
        protected TagRepositoryInterface $tagRepository
    )
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $tags = $this->tagRepository->pagination(20);
        return view('tag::index', compact('tags'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('tag::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param CreateTagRequest $request
     * @return Renderable
     */
    public function store(CreateTagRequest $request)
    {
        $tag = $this->tagRepository->create($request->all());
        if (config('elasticsearch.enabled')) {
            dispatch(new IndexTagElasticsearch($tag->id));
        }
        return redirect()->route('tag.index')->withToastSuccess('Create success');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Application|Factory|View|void
     */
    public function show($id)
    {
        if ($tag = $this->tagRepository->find($id))
            return view('tag::show', compact('tag'));
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Application|Factory|View|void
     */
    public function edit($id)
    {
        if ($tag = $this->tagRepository->find($id))
            return view('tag::edit', compact('tag'));
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateTagRequest $request
     * @param int $id
     * @return void
     */
    public function update(UpdateTagRequest $request, $id)
    {
        if ($this->tagRepository->find($id)) {
            $this->tagRepository->update($id, $request->all());
            if (config('elasticsearch.enabled')) {
                dispatch(new IndexTagElasticsearch($id));
            }
            return redirect()->route('tag.index')->withToastSuccess('Update success');
        }
        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        if ($this->tagRepository->find($id)) {
            $this->tagRepository->delete($id);
            if (config('elasticsearch.enabled')) {
                dispatch(new IndexTagElasticsearch($id));
            }
            return redirect()->route('tag.index')->withToastSuccess('Delete success');
        }
        abort(404);
    }

    public function search(Request $request)
    {
        $txt = $request->txt ?? '';
        $number = $request->number ?? 10;
        $tags = $this->tagRepository->search([
            ['name', 'like', "%$txt%"]
        ], limit: $number);

        return response()->json(!empty($tags['data']) ? $tags['data']->pluck('name') : []);
    }
}
