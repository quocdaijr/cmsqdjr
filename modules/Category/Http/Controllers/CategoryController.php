<?php

namespace Modules\Category\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Category\Http\Requests\CreateCategoryRequest;
use Modules\Category\Http\Requests\UpdateCategoryRequest;
use Modules\Category\Jobs\IndexCategoryElasticsearch;
use Modules\Category\Repositories\Interfaces\CategoryRepositoryInterface;
use Modules\Core\Http\Controllers\CoreController;

class CategoryController extends CoreController
{
    public function __construct(
        protected CategoryRepositoryInterface $categoryRepository
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
        $categories = $this->categoryRepository->pagination(20);
        return view('category::index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('category::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param CreateCategoryRequest $request
     * @return RedirectResponse
     */
    public function store(CreateCategoryRequest $request)
    {
        $category = $this->categoryRepository->create($request->all());
        if (config('elasticsearch.enabled')) {
            dispatch(new IndexCategoryElasticsearch($category->id));
        }
        return redirect()->route('category.index')->withToastSuccess('Create success');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Application|Factory|View|void
     */
    public function show($id)
    {
        if ($category = $this->categoryRepository->find($id))
            return view('category::show', compact('category'));
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Application|Factory|View|void
     */
    public function edit($id)
    {
        if ($category = $this->categoryRepository->find($id))
            return view('category::edit', compact('category'));
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateCategoryRequest $request
     * @param int $id
     * @return void
     */
    public function update(UpdateCategoryRequest $request, $id)
    {
        if ($this->categoryRepository->find($id)) {
            $this->categoryRepository->update($id, $request->all());
            if (config('elasticsearch.enabled')) {
                dispatch(new IndexCategoryElasticsearch($id));
            }
            return redirect()->route('category.index')->withToastSuccess('Update success');
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
        if ($this->categoryRepository->find($id)) {
            $this->categoryRepository->delete($id);
            if (config('elasticsearch.enabled')) {
                dispatch(new IndexCategoryElasticsearch($id));
            }
            return redirect()->route('category.index')->withToastSuccess('Delete success');
        }
        abort(404);
    }
}
