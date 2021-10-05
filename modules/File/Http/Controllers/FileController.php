<?php

namespace Modules\File\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Constants\CoreConstant;
use Modules\Core\Http\Controllers\CoreController;
use Modules\File\Http\Requests\CreateFileRequest;
use Modules\File\Http\Requests\UpdateFileRequest;
use Modules\File\Repositories\Interfaces\FileRepositoryInterface;
use Modules\File\Services\FileService;
use function React\Promise\all;

class FileController extends CoreController
{
    public function __construct(
        protected FileRepositoryInterface $fileRepository,
        protected FileService             $fileService
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
        return view('file::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('file::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param CreateFileRequest $request
     * @return JsonResponse
     */
    public function store(CreateFileRequest $request)
    {
        $data = $this->fileService->store($request->file('file'));
        if ($data !== null) {
            $layout = $request->get('layout');
            $response = match ($layout) {
                'ckeditor' => [
                    'id' => $data->id,
                    'fileName' => $data->name,
                    'uploaded' => 1,
                    'url' => getUrlFile($data->path)
                ],
                default => [
                    'id' => $data->id,
                    'fileName' => $data->name,
                    'url' => getUrlFile($data->path)
                ],
            };
            return response()->json($response);
        }
        return response()->json([
            'error' => 'Upload Failed'
        ], 400);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('file::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('file::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateFileRequest $request
     * @param int $id
     * @return RedirectResponse|JsonResponse|void
     */
    public function update(UpdateFileRequest $request, int $id)
    {
        if ($this->fileRepository->find($id)) {
            $data = [
                'title' => $request->title,
                'description' => $request->description
            ];
            if (!empty($request->name))
                $data['name'] = $request->name;
            if (!empty($request->status))
                $data['status'] = $request->status;

            $this->fileRepository->update($id, $data);
            if ($request->ajax())
                return response()->json(['success' => true]);
            else
                return redirect()->route('file.index')->with('success', 'Update success');
        }
        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    public function files(Request $request)
    {
        $page = $request->page ?? 1;
        $perPage = $request->perPage ?? 20;
        $params = [
            ['status' => CoreConstant::STATUS_ACTIVE]
        ];
        if (!empty($request->txt)) {
            $params[] = ['name', 'like', "%$request->txt%"];
        }
        if (!empty($request->type)) {
            $params[] = ['type', $request->type];
        }
        return response()->json($this->fileRepository->search($params, ($page - 1) * $perPage, $perPage, 'id', 'desc'));
    }
}
