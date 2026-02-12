<?php

namespace Modules\Post\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Category\Repositories\Interfaces\CategoryRepositoryInterface;
use Modules\Core\Constants\CoreConstant;
use Modules\Core\Http\Controllers\CoreController;
use Modules\Post\Constants\PostConstant;
use Modules\Post\Http\Requests\CreatePostRequest;
use Modules\Post\Http\Requests\UpdatePostRequest;
use Modules\Post\Jobs\IndexPostElasticsearch;
use Modules\Post\Repositories\Interfaces\PostRepositoryInterface;
use Modules\Tag\Repositories\Interfaces\TagRepositoryInterface;

class PostController extends CoreController
{

    public function __construct(
        protected PostRepositoryInterface              $postRepository,
        protected CategoryRepositoryInterface          $categoryRepository,
        protected TagRepositoryInterface               $tagRepository,
    )
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     * @return Application|Factory|View
     */
    public function index()
    {
        $posts = $this->postRepository->pagination(CoreConstant::PER_PAGE_DEFAULT);
        return view('post::index', compact('posts'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Application|Factory|View
     */
    public function create()
    {
        $categories = $this->categoryRepository->all();
        return view('post::create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     * @param CreatePostRequest $request
     * @return mixed
     */
    public function store(CreatePostRequest $request)
    {
        $data = [
            'name' => $request->name ?? null,
            'title' => $request->title ?? null,
            'slug' => $request->slug ?? null,
            'description' => $request->description ?? null,
            'author' => $request->author ?? null,
            'location' => $request->location ?? null,
            'source' => $request->source ?? null,
            'content' => $request->post('content'),
            'status' => PostConstant::getStatusByAction($request->action ?? null),
            'published_at' => $request->published_at ?? null
        ];

        $post = $this->postRepository->create($data);

        $post->categories()->sync($request->categories);

        $tags = $this->tagRepository->saveFromNames(array_unique($request->tags ?? []));
        $post->tags()->sync(array_unique($tags));

        $post->files()->attach([$request->thumbnail => ['type' => PostConstant::POST_HAS_FILE_TYPE_THUMBNAIL]]);

        if (!empty($request->cover))
            $post->files()->attach([$request->cover => ['type' => PostConstant::POST_HAS_FILE_TYPE_COVER]]);

        // Only index to Elasticsearch if enabled
        if (config('elasticsearch.enabled')) {
            dispatch(new IndexPostElasticsearch($post->id));
        }

        return redirect()->route('post.index')->withToastSuccess('Create success');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Application|Factory|View|void
     */
    public function show(int $id)
    {
        if (!empty($post = $this->postRepository->find($id))) {
            if (!empty($post->content))
                $post->content = adjustedHtmlContent($post->content);
            return view('post::show', compact('post'));
        }
        abort(404);
    }


    /**
     * @param int $id
     * @return Application|Factory|View|void
     */
    public function edit(int $id)
    {
        if (!empty($post = $this->postRepository->find($id))) {
            $categories = $this->categoryRepository->all();
            if (!empty($post->files->toArray())) {
                foreach ($post->files->toArray() as $k => $v) {
                    switch ($v['pivot']['type'] ?? '') {
                        case PostConstant::POST_HAS_FILE_TYPE_THUMBNAIL:
                            $post->thumbnail['id'] = $v['id'];
                            $post->thumbnail['url'] = getUrlFile($v['path']);
                            break;
                        case PostConstant::POST_HAS_FILE_TYPE_COVER:
                            $post->cover['id'] = $v['id'];
                            $post->cover['url'] = getUrlFile($v['path']);
                            break;
                    }
                }
                if (!empty($post->content))
                    $post->content = adjustedHtmlContent($post->content);
            }
            return view('post::edit', compact('post', 'categories'));
        }
        abort(404);
    }

    /**
     * @param UpdatePostRequest $request
     * @param int $id
     * @return RedirectResponse|void
     */
    public function update(UpdatePostRequest $request, int $id)
    {
        if (!empty($oldPost = $this->postRepository->find($id))) {
            $data = [
                'name' => $request->name ?? null,
                'title' => $request->title ?? null,
                'slug' => $request->slug ?? null,
                'description' => $request->description ?? null,
                'author' => $request->author ?? null,
                'location' => $request->location ?? null,
                'source' => $request->source ?? null,
                'content' => $request->post('content'),
                'status' => PostConstant::getStatusByAction($request->action ?? null, $oldPost->status ?? null),
                'published_at' => $request->published_at ?? null
            ];

            $oldPost->categories()->sync($request->categories);

            $tags = $this->tagRepository->saveFromNames(array_unique($request->tags ?? []));

            $oldPost->tags()->sync(array_unique($tags));

            $oldPost->files()->sync([$request->thumbnail => ['type' => PostConstant::POST_HAS_FILE_TYPE_THUMBNAIL]]);

            if (!empty($oldPost->files->toArray())) {
                foreach ($oldPost->files->toArray() as $k => $v) {
                    if (!empty($v['pivot']['type']) && $v['pivot']['type'] == PostConstant::POST_HAS_FILE_TYPE_THUMBNAIL) {
                        $old_cover = $v['id'];
                        break;
                    }
                }
                if (!empty($old_cover))
                    $oldPost->files()->detach([$old_cover => ['type' => PostConstant::POST_HAS_FILE_TYPE_COVER]]);
            }

            if (!empty($request->cover))
                $oldPost->files()->attach([$request->cover => ['type' => PostConstant::POST_HAS_FILE_TYPE_COVER]]);

            $this->postRepository->update($id, $data);

            // Only index to Elasticsearch if enabled
            if (config('elasticsearch.enabled')) {
                dispatch(new IndexPostElasticsearch($id));
            }

            return redirect()->route('post.index')->with('success', 'Update success');
        }
        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return RedirectResponse|void
     */
    public function destroy(int $id)
    {
        if (!empty($this->postRepository->find($id))) {
            $data = [
                'status' => PostConstant::STATUS_TRASH
            ];
            $this->postRepository->update($id, $data);

            // Only index to Elasticsearch if enabled
            if (config('elasticsearch.enabled')) {
                dispatch(new IndexPostElasticsearch($id));
            }

            return redirect()->route('post.index')->with('success', 'Delete success');
        }
        abort(404);
    }
}
